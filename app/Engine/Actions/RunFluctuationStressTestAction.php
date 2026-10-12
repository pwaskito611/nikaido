<?php

declare(strict_types=1);

namespace App\Engine\Actions;

use InvalidArgumentException;

class RunFluctuationStressTestAction
{
    private const MODES = ['adverse', 'favorable', 'combined'];

    /**
     * @param  array{initial_capital: float, order_history: list<array<string, mixed>>}  $backtest
     * @return array{
     *     baseline: array<string, int|float|null>,
     *     scenario: array<string, int|float|null>,
     *     orders: list<array<string, mixed>>,
     *     adverse_stress: float,
     *     favorable_stress: float
     * }
     */
    public function execute(
        array $backtest,
        string $mode,
        float $adverseStress,
        float $favorableStress,
    ): array {
        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException('Stress mode must be adverse, favorable, or combined.');
        }
        if ($adverseStress < 0 || $adverseStress > 1 || $favorableStress < 0 || $favorableStress > 1) {
            throw new InvalidArgumentException('Stress values must be between 0 and 1.');
        }

        $baselineOrders = $backtest['order_history'];
        $scenarioOrders = [];
        $capital = (float) $backtest['initial_capital'];

        foreach ($baselineOrders as $order) {
            $scenarioOrder = $order;
            $originalReason = $order['close_reason'];
            $scenarioReason = $originalReason;
            $scenarioCloseDate = $order['close_date'];
            $scenarioClosePrice = $order['close_price'];

            if (! in_array($originalReason, ['stop_loss', 'take_profit'], true)) {
                $isLong = $order['signal'] === 'long';
                $adverse = $this->extreme($order, 'adverse_extreme', 'closest_to_stop_loss');
                $favorable = $this->extreme($order, 'favorable_extreme', 'closest_to_take_profit');

                if ($mode !== 'favorable' && $adverse !== null
                    && $this->isAdverseExcursion(
                        $adverse['price'],
                        (float) $order['entry_price'],
                        (float) $order['stop_loss'],
                        $isLong,
                    )) {
                    $stress = $mode === 'adverse' || $mode === 'combined' ? $adverseStress : 0.0;
                    $adverse['price'] = $isLong
                        ? (float) $order['stop_loss'] + (1 - $stress) * ($adverse['price'] - (float) $order['stop_loss'])
                        : (float) $order['stop_loss'] - (1 - $stress) * ((float) $order['stop_loss'] - $adverse['price']);
                }

                if ($mode !== 'adverse' && $favorable !== null
                    && $this->isFavorableExcursion(
                        $favorable['price'],
                        (float) $order['entry_price'],
                        (float) $order['take_profit'],
                        $isLong,
                    )) {
                    $stress = $mode === 'favorable' || $mode === 'combined' ? $favorableStress : 0.0;
                    $favorable['price'] = $isLong
                        ? (float) $order['take_profit'] - (1 - $stress) * ((float) $order['take_profit'] - $favorable['price'])
                        : (float) $order['take_profit'] + (1 - $stress) * ($favorable['price'] - (float) $order['take_profit']);
                }

                $stopHit = $adverse !== null
                    && $this->hasReachedStop($adverse['price'], (float) $order['stop_loss'], $isLong);
                $takeProfitHit = $favorable !== null
                    && $this->hasReachedTakeProfit($favorable['price'], (float) $order['take_profit'], $isLong);

                if ($stopHit) {
                    $scenarioReason = 'stop_loss';
                    $scenarioCloseDate = $adverse['date'];
                    $scenarioClosePrice = (float) $order['stop_loss'];
                } elseif ($takeProfitHit) {
                    $scenarioReason = 'take_profit';
                    $scenarioCloseDate = $favorable['date'];
                    $scenarioClosePrice = (float) $order['take_profit'];
                }

                $scenarioOrder['stress_adverse_extreme'] = $adverse;
                $scenarioOrder['stress_favorable_extreme'] = $favorable;
            }

            $scenarioOrder['close_reason'] = $scenarioReason;
            $scenarioOrder['close_date'] = $scenarioCloseDate;
            $scenarioOrder['close_price'] = $scenarioClosePrice;
            if ($scenarioClosePrice !== null) {
                $priceMove = $order['signal'] === 'long'
                    ? (float) $scenarioClosePrice - (float) $order['entry_price']
                    : (float) $order['entry_price'] - (float) $scenarioClosePrice;
                $scenarioOrder['profit_loss'] = $priceMove * (float) $order['position_size'];
                $capital += $scenarioOrder['profit_loss'];
                $scenarioOrder['capital_after_close'] = $capital;
            } else {
                $scenarioOrder['profit_loss'] = null;
                $scenarioOrder['capital_after_close'] = null;
            }
            $scenarioOrders[] = $scenarioOrder;
        }

        return [
            'baseline' => $this->metrics($baselineOrders, (float) $backtest['initial_capital']),
            'scenario' => $this->metrics($scenarioOrders, (float) $backtest['initial_capital']),
            'orders' => $scenarioOrders,
            'adverse_stress' => $adverseStress,
            'favorable_stress' => $favorableStress,
        ];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array{price: float, date: ?string, source: ?string}|null
     */
    private function extreme(array $order, string $key, string $fallbackKey): ?array
    {
        $extreme = $order[$key] ?? $order[$fallbackKey] ?? null;
        if (! is_array($extreme) || ! is_numeric($extreme['price'] ?? null)) {
            return null;
        }

        return [
            'price' => (float) $extreme['price'],
            'date' => $extreme['date'] ?? null,
            'source' => $extreme['source'] ?? null,
        ];
    }

    private function hasReachedStop(float $price, float $stopLoss, bool $isLong): bool
    {
        return $isLong ? $price <= $stopLoss : $price >= $stopLoss;
    }

    private function hasReachedTakeProfit(float $price, float $takeProfit, bool $isLong): bool
    {
        return $isLong ? $price >= $takeProfit : $price <= $takeProfit;
    }

    private function isAdverseExcursion(float $price, float $entry, float $stopLoss, bool $isLong): bool
    {
        return $isLong
            ? $price < $entry && $price > $stopLoss
            : $price > $entry && $price < $stopLoss;
    }

    private function isFavorableExcursion(float $price, float $entry, float $takeProfit, bool $isLong): bool
    {
        return $isLong
            ? $price > $entry && $price < $takeProfit
            : $price < $entry && $price > $takeProfit;
    }

    /**
     * @param  list<array<string, mixed>>  $orders
     * @return array{
     *     net_profit: float,
     *     expectancy: float,
     *     profit_factor: ?float,
     *     max_drawdown: float,
     *     trade_count: int,
     *     stop_loss_count: int,
     *     take_profit_count: int,
     *     stop_loss_frequency: float,
     *     take_profit_frequency: float
     * }
     */
    private function metrics(array $orders, float $initialCapital): array
    {
        $closedOrders = array_values(array_filter(
            $orders,
            static fn (array $order): bool => $order['close_date'] !== null && $order['profit_loss'] !== null,
        ));
        $netProfit = array_sum(array_column($closedOrders, 'profit_loss'));
        $grossProfit = array_sum(array_map(
            static fn (array $order): float => max((float) $order['profit_loss'], 0),
            $closedOrders,
        ));
        $grossLoss = abs(array_sum(array_map(
            static fn (array $order): float => min((float) $order['profit_loss'], 0),
            $closedOrders,
        )));
        $stopLossCount = count(array_filter(
            $closedOrders,
            static fn (array $order): bool => $order['close_reason'] === 'stop_loss',
        ));
        $takeProfitCount = count(array_filter(
            $closedOrders,
            static fn (array $order): bool => $order['close_reason'] === 'take_profit',
        ));
        $capital = $initialCapital;
        $peakCapital = $initialCapital;
        $maximumDrawdown = 0.0;

        foreach ($closedOrders as $order) {
            $capital += (float) $order['profit_loss'];
            $peakCapital = max($peakCapital, $capital);
            $maximumDrawdown = max($maximumDrawdown, $peakCapital - $capital);
        }

        $tradeCount = count($closedOrders);

        return [
            'net_profit' => $netProfit,
            'expectancy' => $tradeCount > 0 ? $netProfit / $tradeCount : 0.0,
            'profit_factor' => $grossLoss > 0 ? $grossProfit / $grossLoss : ($grossProfit > 0 ? null : 0.0),
            'max_drawdown' => $maximumDrawdown,
            'trade_count' => $tradeCount,
            'stop_loss_count' => $stopLossCount,
            'take_profit_count' => $takeProfitCount,
            'stop_loss_frequency' => $tradeCount > 0 ? $stopLossCount / $tradeCount * 100 : 0.0,
            'take_profit_frequency' => $tradeCount > 0 ? $takeProfitCount / $tradeCount * 100 : 0.0,
        ];
    }
}
