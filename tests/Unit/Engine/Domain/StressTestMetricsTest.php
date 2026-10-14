<?php

declare(strict_types=1);

namespace Tests\Unit\Engine\Domain;

use App\Features\Engine\Domain\StressTestMetric;
use App\Features\Engine\Domain\StressTestMetricName;
use App\Features\Engine\Domain\StressTestMetrics;
use Exact\Collection\Vec;
use Exact\Type\Option\Option;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class StressTestMetricsTest extends TestCase
{
    public function test_metric_values_must_be_finite_numbers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StressTestMetric(StressTestMetricName::NetProfit, Option::some(INF));
    }

    public function test_metric_collection_must_be_complete_and_unique(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StressTestMetrics(Vec::of(
            new StressTestMetric(StressTestMetricName::NetProfit, Option::some(0.0)),
            new StressTestMetric(StressTestMetricName::NetProfit, Option::some(0.0)),
        ));
    }

    public function test_absent_metric_value_serializes_as_null(): void
    {
        $metrics = Vec::fromArray(StressTestMetricName::cases())->map(
            static fn (StressTestMetricName $name): StressTestMetric => new StressTestMetric(
                $name,
                $name === StressTestMetricName::ProfitFactor ? Option::none() : Option::some(0.0),
            ),
        );

        $this->assertNull((new StressTestMetrics($metrics))->toArray()['profit_factor']);
    }
}
