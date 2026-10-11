<?php

namespace Tests\Unit\Engine\Services;

use App\Engine\Enums\StrategySignal;
use App\Engine\Services\StrategyClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use UnexpectedValueException;

class StrategyClientTest extends TestCase
{
    public function test_it_posts_without_a_body_and_reads_the_strategy_decision(): void
    {
        config(['services.strategy.url' => 'http://strategy.test']);
        Http::fake([
            'strategy.test/api/strategy/' => Http::response([
                'signal' => 'long',
                'stop_loss' => 1.09,
                'take_profit' => 1.12,
            ], 200),
        ]);

        $result = (new StrategyClient(app(HttpFactory::class)))->execute();

        $this->assertSame(StrategySignal::Long, $result->signal);
        $this->assertSame(1.09, $result->stopLoss);
        $this->assertSame(1.12, $result->takeProfit);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'http://strategy.test/api/strategy/'
            && $request->data() === []);
    }

    public function test_it_rejects_an_empty_response(): void
    {
        Http::fake([
            '*/api/strategy/' => Http::response('', 200),
        ]);

        $this->expectException(UnexpectedValueException::class);

        (new StrategyClient(app(HttpFactory::class)))->execute();
    }

    public function test_it_rejects_a_non_signal_response(): void
    {
        Http::fake([
            '*/api/strategy/' => Http::response(['signal' => 'hold'], 200),
        ]);

        $this->expectException(UnexpectedValueException::class);

        (new StrategyClient(app(HttpFactory::class)))->execute();
    }

    public function test_it_requires_stop_loss_and_take_profit_for_entry_signals(): void
    {
        Http::fake([
            '*/api/strategy/' => Http::response(['signal' => 'short', 'stop_loss' => 1.12], 200),
        ]);

        $this->expectException(UnexpectedValueException::class);

        (new StrategyClient(app(HttpFactory::class)))->execute();
    }
}
