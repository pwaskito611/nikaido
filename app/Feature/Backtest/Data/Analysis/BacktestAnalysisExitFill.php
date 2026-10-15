<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data\Analysis;

use Exact\Type\Option\Option;

final readonly class BacktestAnalysisExitFill
{
    public function __construct(
        public BacktestAnalysisTimestamp $date,
        public Option $capitalAfter,
    ) {}

    /**
     * @param  array<string, mixed>  $fill
     */
    public static function fromArray(array $fill): self
    {
        $capitalAfter = $fill['capital_after'] ?? null;

        return new self(
            BacktestAnalysisTimestamp::fromPersistedValue($fill['date'] ?? null, 'exit fill date'),
            is_numeric($capitalAfter) && is_finite((float) $capitalAfter)
                ? Option::some((float) $capitalAfter)
                : Option::none(),
        );
    }
}
