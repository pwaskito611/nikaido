<?php

namespace Tests\Unit\Engine\Repositories;

use App\Features\Engine\Models\MarketPair;
use App\Features\Engine\Models\MarketPrice;
use App\Features\Engine\Models\MarketType;
use App\Features\Engine\Repositories\MarketPairRepository;
use App\Features\Engine\Repositories\MarketPriceRepository;
use App\Features\Engine\Repositories\MarketTypeRepository;
use App\Features\Workbench\Domain\MarketPairs\MarketPairDefinition;
use App\Features\Workbench\Domain\MarketPairs\MarketPairQuantityUnit;
use Exact\Type\Option\Option;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RepositoriesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[DataProvider('repositories')]
    public function test_find_returns_the_matching_record(string $repositoryClass, string $modelClass): void
    {
        $record = Mockery::mock($modelClass);
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('find')->once()->with(42)->andReturn($record);

        $repository = $this->repository($repositoryClass, $modelClass, $builder);

        $this->assertSame($record, $repository->find(42));
    }

    #[DataProvider('repositories')]
    public function test_find_returns_null_when_record_does_not_exist(string $repositoryClass, string $modelClass): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('find')->once()->with(42)->andReturnNull();

        $repository = $this->repository($repositoryClass, $modelClass, $builder);

        $this->assertNull($repository->find(42));
    }

    #[DataProvider('repositories')]
    public function test_create_returns_the_created_record(string $repositoryClass, string $modelClass): void
    {
        $input = $repositoryClass === MarketPairRepository::class
            ? MarketPairDefinition::create(1, 'EUR', 'USD', MarketPairQuantityUnit::Units, Option::none())
            : ['name' => 'Test'];
        $attributes = $input instanceof MarketPairDefinition
            ? [
                'market_type_id' => 1,
                'base' => 'EUR',
                'quote' => 'USD',
                'quantity_unit' => 'units',
                'contract_size' => '1',
            ]
            : $input;
        $record = Mockery::mock($modelClass);
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('create')->once()->with($attributes)->andReturn($record);

        $repository = $this->repository($repositoryClass, $modelClass, $builder);

        $this->assertSame($record, $repository->create($input));
    }

    #[DataProvider('repositories')]
    public function test_update_updates_and_returns_the_record(string $repositoryClass, string $modelClass): void
    {
        $input = $repositoryClass === MarketPairRepository::class
            ? MarketPairDefinition::create(1, 'GBP', 'USD', MarketPairQuantityUnit::Units, Option::none())
            : ['name' => 'Updated'];
        $attributes = $input instanceof MarketPairDefinition
            ? [
                'market_type_id' => 1,
                'base' => 'GBP',
                'quote' => 'USD',
                'quantity_unit' => 'units',
                'contract_size' => '1',
            ]
            : $input;
        $record = Mockery::mock($modelClass);
        $record->shouldReceive('update')->once()->with($attributes)->andReturnTrue();

        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('findOrFail')->once()->with(42)->andReturn($record);

        $repository = $this->repository($repositoryClass, $modelClass, $builder);

        $this->assertSame($record, $repository->update(42, $input));
    }

    #[DataProvider('repositories')]
    public function test_delete_deletes_the_record(string $repositoryClass, string $modelClass): void
    {
        $record = Mockery::mock($modelClass);
        $record->shouldReceive('delete')->once()->andReturnTrue();

        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('findOrFail')->once()->with(42)->andReturn($record);

        $repository = $this->repository($repositoryClass, $modelClass, $builder);

        $this->assertTrue($repository->delete(42));
    }

    public static function repositories(): array
    {
        return [
            'market pair' => [MarketPairRepository::class, MarketPair::class],
            'market price' => [MarketPriceRepository::class, MarketPrice::class],
            'market type' => [MarketTypeRepository::class, MarketType::class],
        ];
    }

    private function repository(string $repositoryClass, string $modelClass, Builder $builder): object
    {
        $model = Mockery::mock($modelClass);
        $model->shouldReceive('newQuery')->once()->andReturn($builder);

        return new $repositoryClass($model);
    }
}
