<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Fundamentals;

use App\Feature\Fundamentals\Action\FindActiveFundamentalImportSessionAction;
use App\Feature\Fundamentals\Action\ReceiveFundamentalObservationsAction;
use App\Feature\Fundamentals\Model\FundamentalImportSession;
use App\Feature\Fundamentals\Repository\FundamentalImportSessionRepository;
use App\Feature\Fundamentals\Repository\FundamentalObservationImportRepository;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class ReceiveFundamentalObservationsActionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_receive_action_imports_the_batch_for_the_session_fundamental(): void
    {
        $session = new FundamentalImportSession(['fundamental_id' => 23]);
        $batch = [[
            'code' => 'CPI',
            'name' => 'Consumer Price Index',
            'datetime' => '2024-09-01 00:00:00',
            'released_at' => '2024-10-10 08:30:00',
            'value' => 1.1,
        ]];
        $repository = Mockery::mock(FundamentalObservationImportRepository::class);
        $repository->shouldReceive('import')
            ->once()
            ->with(23, $batch)
            ->andReturn(['imported' => 1, 'inserted' => 1, 'updated' => 0]);

        $result = (new ReceiveFundamentalObservationsAction($repository))->execute($session, $batch);

        $this->assertSame(['imported' => 1, 'inserted' => 1, 'updated' => 0], $result);
    }

    public function test_session_lookup_does_not_query_repository_for_missing_token(): void
    {
        $repository = Mockery::mock(FundamentalImportSessionRepository::class);
        $repository->shouldNotReceive('findActiveByToken');

        $result = (new FindActiveFundamentalImportSessionAction($repository))->execute(null);

        $this->assertNull($result);
    }

    public function test_session_lookup_delegates_hashable_token_lookup_to_repository(): void
    {
        $token = 'temporary-token';
        $session = new FundamentalImportSession(['fundamental_id' => 23]);
        $repository = Mockery::mock(FundamentalImportSessionRepository::class);
        $repository->shouldReceive('findActiveByToken')->once()->with($token)->andReturn($session);

        $result = (new FindActiveFundamentalImportSessionAction($repository))->execute($token);

        $this->assertSame($session, $result);
    }
}
