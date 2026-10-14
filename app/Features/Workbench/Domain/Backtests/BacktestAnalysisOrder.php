<?php

declare(strict_types=1);

namespace App\Features\Workbench\Domain\Backtests;

use Exact\Collection\Vec;
use Exact\Type\Option\Option;
use UnexpectedValueException;

final readonly class BacktestAnalysisOrder
{
    /**
     * @param  Vec<BacktestAnalysisExitFill>  $exitFills
     */
    private function __construct(
        public BacktestAnalysisTimestamp $openDate,
        public Option $closeDate,
        public Option $profitLoss,
        public Option $realizedProfitLoss,
        public Option $capitalAtOpen,
        public Option $capitalAfterClose,
        public bool $portfolioEntryRejected,
        public Vec $exitFills,
        private BacktestPresentationData $presentationData,
    ) {}

    /**
     * Decode a session/API record at the action boundary.
     *
     * @param  array<string, mixed>  $order
     */
    public static function fromArray(array $order): self
    {
        $closeDate = $order['close_date'] ?? null;

        $exitFills = $order['exit_fills'] ?? [];
        if (! is_array($exitFills) || ! array_is_list($exitFills)) {
            throw new UnexpectedValueException('Backtest exit fills must be a list.');
        }

        return new self(
            openDate: BacktestAnalysisTimestamp::fromPersistedValue($order['open_date'] ?? null, 'order opening date'),
            closeDate: $closeDate === null
                ? Option::none()
                : Option::some(BacktestAnalysisTimestamp::fromPersistedValue($closeDate, 'order closing date')),
            profitLoss: self::numericOption($order['profit_loss'] ?? null),
            realizedProfitLoss: self::numericOption($order['realized_profit_loss'] ?? null),
            capitalAtOpen: self::numericOption($order['capital_at_open'] ?? null),
            capitalAfterClose: self::numericOption($order['capital_after_close'] ?? null),
            portfolioEntryRejected: (bool) ($order['portfolio_entry_rejected'] ?? false),
            exitFills: Vec::fromArray($exitFills)->map(
                static function (mixed $fill): BacktestAnalysisExitFill {
                    if (! is_array($fill)) {
                        throw new UnexpectedValueException('Every backtest exit fill must be a record.');
                    }

                    return BacktestAnalysisExitFill::fromArray($fill);
                },
            ),
            presentationData: BacktestPresentationData::fromExternal(self::normalizeNonFiniteNumbers($order)),
        );
    }

    /** @return array<string, mixed> */
    public function toPresentationArray(): array
    {
        return $this->presentationData->toExternal();
    }

    private static function numericOption(mixed $value): Option
    {
        return is_numeric($value) && is_finite((float) $value)
            ? Option::some((float) $value)
            : Option::none();
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private static function normalizeNonFiniteNumbers(array $order): array
    {
        foreach (['profit_loss', 'realized_profit_loss', 'capital_at_open', 'capital_after_close'] as $key) {
            if (isset($order[$key]) && is_numeric($order[$key]) && ! is_finite((float) $order[$key])) {
                $order[$key] = null;
            }
        }

        return $order;
    }
}
