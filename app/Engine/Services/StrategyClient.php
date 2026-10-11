<?php

namespace App\Engine\Services;

use App\Engine\Enums\StrategySignal;
use App\Engine\Models\StrategyDecision;
use Illuminate\Http\Client\Factory as HttpFactory;
use UnexpectedValueException;

class StrategyClient
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function execute(): StrategyDecision
    {
        $response = $this->http
            ->baseUrl(rtrim((string) config('services.strategy.url'), '/'))
            ->send('POST', '/api/strategy/');

        $response->throw();

        $body = $response->json();

        if (! is_array($body) || ! is_string($body['signal'] ?? null)) {
            throw new UnexpectedValueException('The Strategy endpoint must return a signal and optional stop-loss/take-profit values.');
        }

        $signal = StrategySignal::tryFrom(strtolower($body['signal']));

        if ($signal === null) {
            throw new UnexpectedValueException('The Strategy endpoint returned an unsupported signal.');
        }

        $stopLoss = $this->price($body, 'stop_loss');
        $takeProfit = $this->price($body, 'take_profit');

        if (in_array($signal, [StrategySignal::Long, StrategySignal::Short], true)
            && ($stopLoss === null || $takeProfit === null)) {
            throw new UnexpectedValueException('Long and short signals must include numeric stop-loss and take-profit values.');
        }

        return new StrategyDecision($signal, $stopLoss, $takeProfit);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function price(array $body, string $key): ?float
    {
        $value = $body[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_int($value) && ! is_float($value)) {
            throw new UnexpectedValueException("The Strategy endpoint field [{$key}] must be numeric.");
        }

        return (float) $value;
    }
}
