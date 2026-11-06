<?php

declare(strict_types=1);

namespace App\Feature\News\Repository;

use App\Feature\News\Data\NewsDefinition;
use App\Feature\News\Model\NewsItem;
use App\Feature\News\Model\NewsMarketPairImpact;
use DateTimeImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Nikaido\Strategy\Models\NewsDataCollection;
use Nikaido\Strategy\Models\NewsItemData;

class NewsRepository
{
    public function paginate(): LengthAwarePaginator
    {
        return NewsItem::query()
            ->with(['impacts.marketPair.marketType'])
            ->withCount('impacts')
            ->orderByDesc('published_at')
            ->paginate(20);
    }

    public function find(int $id): NewsItem
    {
        return NewsItem::query()->with(['impacts.marketPair.marketType'])->findOrFail($id);
    }

    public function create(NewsDefinition $definition): NewsItem
    {
        return DB::transaction(function () use ($definition): NewsItem {
            $item = $this->upsertItem($this->itemValues($definition));
            $this->saveImpact($item, $definition->marketPairId, $this->impactValues($definition));

            return $this->find((int) $item->id);
        });
    }

    public function update(int $id, NewsDefinition $definition): NewsItem
    {
        return DB::transaction(function () use ($id, $definition): NewsItem {
            $item = NewsItem::query()->lockForUpdate()->findOrFail($id);
            $item->fill($this->itemValues($definition));
            $item->dedupe_key = $this->dedupeKey($this->itemValues($definition));
            $item->save();
            $this->saveImpact($item, $definition->marketPairId, $this->impactValues($definition));

            return $this->find($id);
        });
    }

    public function delete(int $id): void
    {
        NewsItem::query()->findOrFail($id)->delete();
    }

    /**
     * @param  list<array{
     *     source:string, external_id:?string, title:string, content:string,
     *     published_at:string, available_at:string, language:?string,
     *     relevance_score:float, impact_score:float, impact_lag_seconds:int,
     *     impact_duration_seconds:int, confidence_score:float, assessment_method:string
     * }>  $articles
     * @return array{imported:int, inserted:int, updated:int}
     */
    public function importForPair(int $pairId, array $articles): array
    {
        return DB::transaction(function () use ($pairId, $articles): array {
            $inserted = 0;
            $updated = 0;

            foreach ($articles as $article) {
                $itemValues = $article;
                $itemValues['dedupe_key'] = $this->dedupeKey($article);
                $itemValues = array_intersect_key($itemValues, array_flip([
                    'source', 'external_id', 'dedupe_key', 'title', 'content',
                    'published_at', 'available_at', 'language',
                ]));

                $item = NewsItem::query()->where('dedupe_key', $itemValues['dedupe_key'])->lockForUpdate()->first();
                if ($item === null) {
                    $item = NewsItem::query()->create($itemValues);
                    $inserted++;
                } else {
                    $item->fill($itemValues);
                    $item->save();
                    $updated++;
                }

                $this->saveImpact($item, $pairId, [
                    'relevance_score' => $article['relevance_score'],
                    'impact_score' => $article['impact_score'],
                    'impact_lag_seconds' => $article['impact_lag_seconds'],
                    'impact_duration_seconds' => $article['impact_duration_seconds'],
                    'confidence_score' => $article['confidence_score'],
                    'assessment_method' => $article['assessment_method'],
                ]);
            }

            return ['imported' => count($articles), 'inserted' => $inserted, 'updated' => $updated];
        });
    }

    public function forBacktest(int $pairId, string $from, string $to): NewsDataCollection
    {
        $fromTime = new DateTimeImmutable($from);
        $toTime = new DateTimeImmutable($to);
        $earliestRelevantPublication = $fromTime->modify('-2 years');
        $rows = NewsMarketPairImpact::query()
            ->join('news_items', 'news_items.id', '=', 'news_market_pair_impacts.news_item_id')
            ->where('news_market_pair_impacts.market_pair_id', $pairId)
            ->where('news_items.available_at', '<=', $to)
            ->where('news_items.published_at', '>=', $earliestRelevantPublication->format('Y-m-d H:i:s'))
            ->where('news_items.published_at', '<=', $to)
            ->select([
                'news_items.id',
                'news_items.source',
                'news_items.title',
                'news_items.content',
                'news_items.published_at',
                'news_items.available_at',
                'news_market_pair_impacts.market_pair_id',
                'news_market_pair_impacts.relevance_score',
                'news_market_pair_impacts.impact_score',
                'news_market_pair_impacts.impact_lag_seconds',
                'news_market_pair_impacts.impact_duration_seconds',
                'news_market_pair_impacts.confidence_score',
                'news_market_pair_impacts.assessment_method',
            ])
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $item = new NewsItemData(
                id: (int) $row->id,
                source: $row->source,
                title: $row->title,
                content: $row->content,
                publishedAt: new DateTimeImmutable($row->published_at),
                availableAt: new DateTimeImmutable($row->available_at),
                marketPairId: (int) $row->market_pair_id,
                relevanceScore: (float) $row->relevance_score,
                impactScore: (float) $row->impact_score,
                impactLagSeconds: (int) $row->impact_lag_seconds,
                impactDurationSeconds: (int) $row->impact_duration_seconds,
                confidenceScore: (float) $row->confidence_score,
                assessmentMethod: $row->assessment_method,
            );
            $publishedInRange = $item->publishedAt >= $fromTime;
            $impactCanOccurInRange = $item->impactStartsAt() <= $toTime
                && $item->impactEndsAt() >= $fromTime
                && $item->availableAt <= $toTime;
            if ($item->publishedAt <= $toTime && ($publishedInRange || $impactCanOccurInRange)) {
                $items[] = $item;
            }
        }

        return new NewsDataCollection($items);
    }

    /** @param array<string,mixed> $values */
    private function upsertItem(array $values): NewsItem
    {
        $values['dedupe_key'] = $this->dedupeKey($values);
        $item = NewsItem::query()->where('dedupe_key', $values['dedupe_key'])->lockForUpdate()->first();
        if ($item === null) {
            return NewsItem::query()->create($values);
        }

        $item->fill($values);
        $item->save();

        return $item;
    }

    /** @return array<string,mixed> */
    private function itemValues(NewsDefinition $definition): array
    {
        return [
            'source' => $definition->source,
            'external_id' => $definition->externalId,
            'title' => $definition->title,
            'content' => $definition->content,
            'published_at' => $definition->publishedAt->format('Y-m-d H:i:s'),
            'available_at' => $definition->availableAt->format('Y-m-d H:i:s'),
            'language' => $definition->language,
        ];
    }

    /** @return array<string,mixed> */
    private function impactValues(NewsDefinition $definition): array
    {
        return [
            'relevance_score' => $definition->relevanceScore,
            'impact_score' => $definition->impactScore,
            'impact_lag_seconds' => $definition->impactLagSeconds,
            'impact_duration_seconds' => $definition->impactDurationSeconds,
            'confidence_score' => $definition->confidenceScore,
            'assessment_method' => $definition->assessmentMethod,
        ];
    }

    /** @param array<string,mixed> $values */
    private function dedupeKey(array $values): string
    {
        $identity = $values['external_id'] !== null && $values['external_id'] !== ''
            ? $values['source'].'|id|'.$values['external_id']
            : $values['source'].'|natural|'.$values['title'].'|'.$values['published_at'].'|'.($values['content'] ?? '');

        return hash('sha256', $identity);
    }

    /** @param array<string,mixed> $values */
    private function saveImpact(NewsItem $item, int $pairId, array $values): void
    {
        $item->impacts()->updateOrCreate(['market_pair_id' => $pairId], $values);
    }
}
