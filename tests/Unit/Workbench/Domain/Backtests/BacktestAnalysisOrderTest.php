<?php

declare(strict_types=1);

namespace Tests\Unit\Workbench\Domain\Backtests;

use App\Feature\Backtest\Data\Analysis\BacktestAnalysisOrder;
use Exact\Collection\Vec;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class BacktestAnalysisOrderTest extends TestCase
{
    public function test_it_decodes_nullable_trade_values_and_partial_exit_fills_into_exact_types(): void
    {
        $payload = [
            'open_date' => '2026-10-12 10:00:00',
            'close_date' => null,
            'profit_loss' => null,
            'realized_profit_loss' => -5,
            'capital_at_open' => 10000,
            'capital_after_close' => null,
            'exit_fills' => [
                [
                    'date' => '2026-10-12 10:01:00',
                    'capital_after' => 9995,
                ],
            ],
        ];

        $order = BacktestAnalysisOrder::fromArray($payload);

        $this->assertTrue($order->closeDate->isNone());
        $this->assertTrue($order->profitLoss->isNone());
        $this->assertSame(-5.0, $order->realizedProfitLoss->getOrNull());
        $this->assertSame(10000.0, $order->capitalAtOpen->getOrNull());
        $this->assertInstanceOf(Vec::class, $order->exitFills);
        $this->assertSame(9995.0, $order->exitFills->first()->capitalAfter->getOrNull());
        $this->assertSame($payload, $order->toPresentationArray());
    }

    public function test_it_represents_invalid_optional_profit_and_loss_as_absent(): void
    {
        $order = BacktestAnalysisOrder::fromArray([
            'open_date' => '2026-10-12 10:00:00',
            'profit_loss' => INF,
        ]);

        $this->assertTrue($order->profitLoss->isNone());
        $this->assertNull($order->toPresentationArray()['profit_loss']);
    }

    public function test_it_rejects_exit_fills_without_a_valid_date(): void
    {
        $this->expectException(UnexpectedValueException::class);

        BacktestAnalysisOrder::fromArray([
            'open_date' => '2026-10-12 10:00:00',
            'exit_fills' => [['capital_after' => 9995]],
        ]);
    }
}
