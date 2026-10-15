<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data\Analysis;

use DateTimeInterface;

final readonly class BacktestAnalysisDecision
{
    private function __construct(
        public BacktestAnalysisTimestamp $timestamp,
        private BacktestPresentationData $presentationData,
    ) {}

    /**
     * @param  array<string, mixed>  $decision
     */
    public static function fromArray(array $decision): self
    {
        $candle = $decision['candle'] ?? null;
        $timestamp = is_array($candle)
            ? ($candle['price_date'] ?? null)
            : (is_object($candle) ? ($candle->price_date ?? null) : null);

        if ($timestamp instanceof DateTimeInterface) {
            $timestamp = $timestamp->format('Y-m-d H:i:s');
        }

        return new self(
            BacktestAnalysisTimestamp::fromPersistedValue($timestamp, 'decision candle timestamp'),
            BacktestPresentationData::fromExternal($decision),
        );
    }

    /** @return array<string, mixed> */
    public function toPresentationArray(): array
    {
        return $this->presentationData->toExternal();
    }
}
