<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketTypes\Model\MarketType;
use App\Feature\News\Model\NewsImportSession;
use App\Feature\News\Model\NewsItem;
use App\Feature\News\Repository\NewsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nikaido\Strategy\Models\StrategyExecutionContext;
use Tests\TestCase;

class NewsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_items_support_crud_with_pair_specific_impact(): void
    {
        [$pair, $otherPair] = $this->createPairs();
        $input = $this->newsInput($pair->id);

        $this->post('/workbench/news', $input)->assertRedirect();
        $news = NewsItem::query()->with('impacts')->sole();
        $this->assertSame($pair->id, $news->impacts->sole()->market_pair_id);
        $this->assertSame('2026-03-01 09:00:00', $news->available_at->format('Y-m-d H:i:s'));
        $this->get('/workbench/news')->assertOk()->assertSee('Central bank announcement')->assertSee('EUR/USD');
        $this->get("/workbench/news/{$news->id}/edit")
            ->assertOk()
            ->assertSee('Available at')
            ->assertSee('Impact score');

        $this->postJson('/api/data-import/news/articles', [])->assertUnauthorized();
        $this->post('/workbench/data-import/news-sessions', ['market_pair_id' => $otherPair->id])
            ->assertRedirect('/workbench/data-import')
            ->assertSessionHas('news_import_token');
        $session = NewsImportSession::query()->sole();
        $token = (string) session('news_import_token');

        $this->put("/workbench/news/{$news->id}", [...$input, 'market_pair_id' => $otherPair->id, 'impact_score' => '0.5'])
            ->assertRedirect();
        $this->assertDatabaseHas('news_market_pair_impacts', [
            'news_item_id' => $news->id,
            'market_pair_id' => $pair->id,
        ]);
        $this->assertDatabaseHas('news_market_pair_impacts', [
            'news_item_id' => $news->id,
            'market_pair_id' => $otherPair->id,
            'impact_score' => '0.50000',
        ]);

        $this->delete("/workbench/news/{$news->id}")->assertRedirect('/workbench/news');
        $this->assertDatabaseMissing('news_items', ['id' => $news->id]);
        $this->assertDatabaseMissing('news_market_pair_impacts', ['news_item_id' => $news->id]);

        $this->post("/workbench/data-import/news-sessions/{$session->id}/stop")
            ->assertRedirect('/workbench/data-import');
        $this->withToken($token)->postJson('/api/data-import/news/articles', [
            'articles' => [$this->apiArticle()],
        ])->assertUnauthorized();
    }

    public function test_pair_scoped_news_api_is_idempotent_and_validates_point_in_time_fields(): void
    {
        [$pair, $otherPair] = $this->createPairs();
        $this->get('/workbench/data-import')
            ->assertOk()
            ->assertSee('NEWS ENDPOINT')
            ->assertSee('/api/data-import/news/articles');
        $this->post('/workbench/data-import/news-sessions', ['market_pair_id' => $pair->id])
            ->assertRedirect('/workbench/data-import')
            ->assertSessionHas('news_import_token');

        $token = (string) session('news_import_token');
        $article = $this->apiArticle();
        $this->withToken($token)->postJson('/api/data-import/news/articles', [
            'articles' => [$article],
        ])->assertAccepted()->assertExactJson([
            'data' => ['imported' => 1, 'inserted' => 1, 'updated' => 0],
        ]);

        $news = NewsItem::query()->sole();
        $this->assertDatabaseHas('news_market_pair_impacts', [
            'news_item_id' => $news->id,
            'market_pair_id' => $pair->id,
            'impact_score' => '-0.60000',
        ]);
        $article['title'] = 'Updated central bank announcement';
        $article['impact_score'] = 0.25;
        $this->withToken($token)->postJson('/api/data-import/news/articles', [
            'articles' => [$article],
        ])->assertAccepted()->assertExactJson([
            'data' => ['imported' => 1, 'inserted' => 0, 'updated' => 1],
        ]);
        $this->assertSame(1, NewsItem::query()->count());
        $this->assertSame('Updated central bank announcement', $news->fresh()->title);
        $this->assertDatabaseMissing('news_market_pair_impacts', ['market_pair_id' => $otherPair->id]);

        $article['available_at'] = '2026-02-28 09:00:00';
        $this->withToken($token)->postJson('/api/data-import/news/articles', [
            'articles' => [$article],
        ])->assertUnprocessable()->assertJsonValidationErrors('articles.0.available_at');
    }

    public function test_news_data_loaded_for_backtest_obeys_availability_and_impact_windows(): void
    {
        [$pair] = $this->createPairs();
        app(NewsRepository::class)->importForPair($pair->id, [[
            ...$this->apiArticle(),
            'published_at' => '2026-03-01 08:00:00',
            'available_at' => '2026-03-01 09:00:00',
            'impact_lag_seconds' => 7200,
            'impact_duration_seconds' => 3600,
        ]]);
        $news = app(NewsRepository::class)->forBacktest(
            $pair->id,
            '2026-03-01 09:30:00',
            '2026-03-01 12:00:00',
        );

        $this->assertCount(0, $news->availableAt(new \DateTimeImmutable('2026-03-01 08:59:59')));
        $this->assertCount(1, $news->availableAt(new \DateTimeImmutable('2026-03-01 09:00:00')));
        $this->assertCount(0, $news->impactActiveAt(new \DateTimeImmutable('2026-03-01 09:59:59')));
        $this->assertCount(1, $news->impactActiveAt(new \DateTimeImmutable('2026-03-01 10:00:00')));
        $this->assertCount(0, $news->impactActiveAt(new \DateTimeImmutable('2026-03-01 11:00:01')));

        $context = new StrategyExecutionContext(10000, 'units', 1, newsData: $news);
        $this->assertSame($news, $context->news());
    }

    /** @return array{MarketPair, MarketPair} */
    private function createPairs(): array
    {
        $type = MarketType::query()->create(['name' => 'Forex']);

        return [
            MarketPair::query()->create(['market_type_id' => $type->id, 'base' => 'EUR', 'quote' => 'USD']),
            MarketPair::query()->create(['market_type_id' => $type->id, 'base' => 'GBP', 'quote' => 'USD']),
        ];
    }

    /** @return array<string, mixed> */
    private function newsInput(int $pairId): array
    {
        return [
            'market_pair_id' => $pairId,
            'source' => 'Manual',
            'external_id' => '',
            'title' => 'Central bank announcement',
            'content' => 'Policy rate increased.',
            'published_at' => '2026-03-01T09:00',
            'available_at' => '2026-03-01T09:00',
            'language' => 'en',
            'relevance_score' => '0.9',
            'impact_score' => '-0.6',
            'impact_lag_seconds' => '0',
            'impact_duration_seconds' => '7200',
            'confidence_score' => '0.8',
            'assessment_method' => 'manual',
        ];
    }

    /** @return array<string, mixed> */
    private function apiArticle(): array
    {
        return [
            'source' => 'provider',
            'external_id' => 'NEWS-123',
            'title' => 'Central bank announcement',
            'content' => 'Policy rate increased.',
            'published_at' => '2026-03-01 09:00:00',
            'available_at' => '2026-03-01 09:00:00',
            'language' => 'en',
            'relevance_score' => 0.9,
            'impact_score' => -0.6,
            'impact_lag_seconds' => 0,
            'impact_duration_seconds' => 7200,
            'confidence_score' => 0.8,
            'assessment_method' => 'provider',
        ];
    }
}
