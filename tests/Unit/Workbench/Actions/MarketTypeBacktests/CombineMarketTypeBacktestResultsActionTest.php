<?php

declare(strict_types=1);

namespace Tests\Unit\Workbench\Actions\MarketTypeBacktests;

use App\Engine\Models\MarketTypeBacktestPairRun;
use App\Engine\Models\MarketTypeBacktestRun;
use App\Workbench\Actions\MarketTypeBacktests\CombineMarketTypeBacktestResultsAction;
use Tests\TestCase;

class CombineMarketTypeBacktestResultsActionTest extends TestCase
{
    public function test_it_combines_pair_decisions_and_updates_shared_capital_in_close_order(): void
    {
        $run = new MarketTypeBacktestRun([
            'market_type_name' => 'Forex',
            'configuration' => [
                'initial_capital' => 10000,
                'timeframe_name' => '1h',
                'from' => '2026-10-10 00:00:00',
                'to' => '2026-10-12 23:59:59',
                'risk_per_trade_percent' => 1,
            ],
        ]);
        $pairRuns = [
            new MarketTypeBacktestPairRun([
                'pair_name' => 'GBP/USD',
                'result' => [
                    'results' => [['candle' => ['price_date' => '2026-10-12 10:02:00']]],
                    'order_history' => [[
                        'id' => 1,
                        'close_date' => '2026-10-12 10:03:00',
                        'profit_loss' => -20,
                    ]],
                ],
            ]),
            new MarketTypeBacktestPairRun([
                'pair_name' => 'EUR/USD',
                'result' => [
                    'results' => [['candle' => ['price_date' => '2026-10-12 10:01:00']]],
                    'order_history' => [[
                        'id' => 1,
                        'close_date' => '2026-10-12 10:02:00',
                        'profit_loss' => 50,
                    ]],
                ],
            ]),
        ];

        $result = (new CombineMarketTypeBacktestResultsAction)->execute($run, $pairRuns);

        $this->assertSame('Overall · Forex', $result['pair']);
        $this->assertSame(['EUR/USD', 'GBP/USD'], array_column($result['results'], 'pair'));
        $this->assertSame(['EUR/USD', 'GBP/USD'], array_column($result['order_history'], 'pair'));
        $this->assertSame(10030.0, $result['ending_capital']);
        $this->assertSame(10050.0, $result['order_history'][0]['capital_after_close']);
        $this->assertSame(10030.0, $result['order_history'][1]['capital_after_close']);
    }
}
