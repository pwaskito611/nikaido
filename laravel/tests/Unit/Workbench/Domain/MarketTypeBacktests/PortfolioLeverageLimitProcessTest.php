<?php

declare(strict_types=1);

namespace Tests\Unit\Workbench\Domain\MarketTypeBacktests;

use App\Feature\Backtest\Data\MarketTypeBacktests\PortfolioManagedOrder;
use App\Feature\Backtest\Process\MarketTypeBacktests\PortfolioLeverageLimitProcess;
use Exact\Collection\Vec;
use Tests\TestCase;

class PortfolioLeverageLimitProcessTest extends TestCase
{
    public function test_it_rejects_a_lower_priority_entry_when_marked_existing_exposure_uses_the_limit(): void
    {
        $orders = Vec::of(
            PortfolioManagedOrder::fromPersistedArray(
                $this->order('EUR/USD', 1, '2026-10-10 10:00:00', 4, 100, 8),
                0,
            ),
            PortfolioManagedOrder::fromPersistedArray(
                $this->order('GBP/USD', 1, '2026-10-10 12:00:00', 7, 100, 2),
                1,
            ),
        );

        $result = (new PortfolioLeverageLimitProcess)->execute(
            1000,
            1,
            $orders,
            [
                ['pair' => 'EUR/USD', 'date' => '2026-10-10 11:00:00', 'close' => 25],
            ],
        );

        $this->assertFalse($result->at(0)->entryRejected);
        $this->assertTrue($result->at(1)->entryRejected);
        $this->assertSame('portfolio_lower_priority_than_open_position', $result->at(1)->rejectionReason);
        $this->assertSame([], $result->at(1)->entryFills->toArray());
    }

    public function test_same_time_entries_are_considered_in_descending_priority_order(): void
    {
        $orders = Vec::of(
            PortfolioManagedOrder::fromPersistedArray(
                $this->order('EUR/USD', 1, '2026-10-10 10:00:00', 6, 100, 2),
                0,
            ),
            PortfolioManagedOrder::fromPersistedArray(
                $this->order('GBP/USD', 1, '2026-10-10 10:00:00', 6, 100, 8),
                1,
            ),
        );

        $result = (new PortfolioLeverageLimitProcess)->execute(1000, 1, $orders, []);

        $this->assertTrue($result->at(0)->entryRejected);
        $this->assertSame('portfolio_lower_priority_than_open_position', $result->at(0)->rejectionReason);
        $this->assertFalse($result->at(1)->entryRejected);
    }

    public function test_cash_flow_entitlement_increases_equity_available_to_a_later_portfolio_entry(): void
    {
        $orders = Vec::of(
            PortfolioManagedOrder::fromPersistedArray(
                $this->order('EUR/USD', 1, '2026-10-10 10:00:00', 9, 100, 5),
                0,
            ),
            PortfolioManagedOrder::fromPersistedArray(
                $this->order('GBP/USD', 1, '2026-10-10 12:00:00', 2, 100, 5),
                1,
            ),
        );

        $result = (new PortfolioLeverageLimitProcess)->execute(
            1000,
            1,
            $orders,
            [
                ['pair' => 'EUR/USD', 'date' => '2026-10-10 11:00:00', 'close' => 100],
                ['pair' => 'GBP/USD', 'date' => '2026-10-10 11:00:00', 'close' => 100],
            ],
            [[
                'date' => '2026-10-10 11:00:00',
                'pair' => 'EUR/USD',
                'order_index' => 0,
                'amount' => 100.0,
            ]],
        );

        $this->assertFalse($result->at(0)->entryRejected);
        $this->assertFalse($result->at(1)->entryRejected);
    }

    /**
     * @return array<string, mixed>
     */
    private function order(
        string $pair,
        int $id,
        string $date,
        float $quantity,
        float $price,
        int $priority,
    ): array {
        return [
            'id' => $id,
            'pair' => $pair,
            'signal' => 'long',
            'open_date' => $date,
            'close_date' => null,
            'entry_price' => $price,
            'entry_price_source' => 'ask_open',
            'stop_loss' => $price - 1,
            'take_profit' => $price + 2,
            'strategy_quantity' => $quantity,
            'quantity_unit' => 'units',
            'quantity_multiplier' => 1.0,
            'pnl_multiplier' => 1.0,
            'requested_position_size' => $quantity,
            'position_size' => $quantity,
            'risk_amount' => $quantity,
            'requested_risk_amount' => $quantity,
            'capital_at_open' => 1000.0,
            'priority' => $priority,
            'entry_fills' => [[
                'quantity' => $quantity,
                'price' => $price,
                'date' => $date,
            ]],
            'exit_fills' => [],
        ];
    }
}
