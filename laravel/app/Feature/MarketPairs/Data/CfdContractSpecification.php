<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Data;

use InvalidArgumentException;

final readonly class CfdContractSpecification
{
    public function __construct(
        public float $contractSize,
        public float $tickSize,
        public float $tickValue,
        public float $minimumQuantity,
        public float $quantityStep,
        public float $initialMarginRate,
    ) {
        foreach ([
            'contract size' => $contractSize,
            'tick size' => $tickSize,
            'tick value' => $tickValue,
            'minimum quantity' => $minimumQuantity,
            'quantity step' => $quantityStep,
        ] as $label => $value) {
            if (! is_finite($value) || $value <= 0) {
                throw new InvalidArgumentException("The CFD {$label} must be a positive finite number.");
            }
        }
        if (! is_finite($initialMarginRate) || $initialMarginRate <= 0 || $initialMarginRate > 100) {
            throw new InvalidArgumentException('Initial margin rate must be greater than 0 and at most 100 percent.');
        }
    }

    /** @param array<string, mixed> $pair */
    public static function fromPersistedPair(array $pair): self
    {
        $values = [];
        foreach ([
            'contract_size' => 'contract size',
            'tick_size' => 'tick size',
            'tick_value' => 'tick value',
            'minimum_quantity' => 'minimum quantity',
            'quantity_step' => 'quantity step',
            'initial_margin_rate' => 'initial margin rate',
        ] as $field => $label) {
            if (! isset($pair[$field]) || ! is_numeric($pair[$field])) {
                throw new InvalidCfdContractSpecification("Complete the CFD contract profile: {$label} is missing.");
            }
            $values[$field] = (float) $pair[$field];
        }

        try {
            return new self(
                contractSize: $values['contract_size'],
                tickSize: $values['tick_size'],
                tickValue: $values['tick_value'],
                minimumQuantity: $values['minimum_quantity'],
                quantityStep: $values['quantity_step'],
                initialMarginRate: $values['initial_margin_rate'],
            );
        } catch (InvalidArgumentException $exception) {
            throw new InvalidCfdContractSpecification(
                'The persisted CFD contract profile is invalid.',
                previous: $exception,
            );
        }
    }

    public function pnlPerPriceUnit(): float
    {
        return $this->tickValue / $this->tickSize;
    }

    public function marginFor(float $positionQuantity, float $price, float $notionalMultiplier = 1): float
    {
        return $positionQuantity * $price * $notionalMultiplier * ($this->initialMarginRate / 100);
    }

    /** @return array<string, float> */
    public function toArray(): array
    {
        return [
            'contract_size' => $this->contractSize,
            'tick_size' => $this->tickSize,
            'tick_value' => $this->tickValue,
            'minimum_quantity' => $this->minimumQuantity,
            'quantity_step' => $this->quantityStep,
            'initial_margin_rate' => $this->initialMarginRate,
            'pnl_currency' => 'USD',
        ];
    }
}
