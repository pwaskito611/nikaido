<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Repository;

use App\Feature\Fundamentals\Data\FundamentalContentDefinition;
use App\Feature\Fundamentals\Data\FundamentalDefinition;
use App\Feature\Fundamentals\Model\Fundamental;
use App\Feature\Fundamentals\Model\FundamentalContent;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Nikaido\Strategy\Models\FundamentalDataCollection;
use Nikaido\Strategy\Models\FundamentalEntityData;
use Nikaido\Strategy\Models\FundamentalObservation;

class FundamentalRepository
{
    public function __construct(
        private readonly Fundamental $model,
        private readonly FundamentalContent $contentModel,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->withCount(['contents', 'marketPairs'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    /** @return Collection<int, Fundamental> */
    public function allForImport(): Collection
    {
        return $this->model->newQuery()
            ->orderBy('name')
            ->get(['id', 'name', 'category']);
    }

    public function findOrFail(int $id): Fundamental
    {
        return $this->model->newQuery()
            ->with('marketPairs')
            ->withCount(['contents', 'marketPairs'])
            ->findOrFail($id);
    }

    public function create(FundamentalDefinition $definition): Fundamental
    {
        if ($definition->pairIds === null) {
            return $this->model->newQuery()->create($this->attributesFrom($definition));
        }

        return DB::transaction(function () use ($definition): Fundamental {
            $fundamental = $this->model->newQuery()->create($this->attributesFrom($definition));
            $fundamental->marketPairs()->sync($definition->pairIds);

            return $fundamental;
        });
    }

    public function update(int $id, FundamentalDefinition $definition): Fundamental
    {
        return DB::transaction(function () use ($id, $definition): Fundamental {
            $fundamental = $this->model->newQuery()->findOrFail($id);
            $fundamental->update($this->attributesFrom($definition));
            if ($definition->pairIds !== null) {
                $fundamental->marketPairs()->sync($definition->pairIds);
            }

            return $fundamental;
        });
    }

    public function delete(int $id): ?bool
    {
        return $this->model->newQuery()->findOrFail($id)->delete();
    }

    public function paginateContents(int $fundamentalId, int $perPage = 50): LengthAwarePaginator
    {
        return $this->contentModel->newQuery()
            ->where('fundamental_id', $fundamentalId)
            ->orderBy('code')
            ->orderBy('datetime')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function findContentOrFail(int $fundamentalId, int $contentId): FundamentalContent
    {
        return $this->contentModel->newQuery()
            ->where('fundamental_id', $fundamentalId)
            ->findOrFail($contentId);
    }

    public function createContent(int $fundamentalId, FundamentalContentDefinition $definition): FundamentalContent
    {
        return $this->contentModel->newQuery()->create([
            'fundamental_id' => $fundamentalId,
            ...$this->contentAttributesFrom($definition),
        ]);
    }

    public function updateContent(
        int $fundamentalId,
        int $contentId,
        FundamentalContentDefinition $definition,
    ): FundamentalContent {
        $content = $this->findContentOrFail($fundamentalId, $contentId);
        $content->update($this->contentAttributesFrom($definition));

        return $content;
    }

    public function deleteContent(int $fundamentalId, int $contentId): ?bool
    {
        return $this->findContentOrFail($fundamentalId, $contentId)->delete();
    }

    public function forMarketPairBacktest(
        int $pairId,
        DateTimeInterface $from,
        DateTimeInterface $to,
    ): FundamentalDataCollection {
        $latestPriorObservation = DB::table('fundamental_contents as prior_contents')
            ->select('prior_contents.datetime')
            ->whereColumn('prior_contents.fundamental_id', 'fundamental_contents.fundamental_id')
            ->whereColumn('prior_contents.code', 'fundamental_contents.code')
            ->whereNotNull('prior_contents.released_at')
            ->where('prior_contents.released_at', '<', $from)
            ->orderByDesc('prior_contents.datetime')
            ->limit(1);

        $fundamentals = $this->model->newQuery()
            ->whereHas('marketPairs', fn ($query) => $query->whereKey($pairId))
            ->with(['contents' => fn ($query) => $query
                ->whereNotNull('released_at')
                ->where('released_at', '<=', $to)
                ->where(fn ($query) => $query
                    ->where('released_at', '>=', $from)
                    ->orWhere(fn ($query) => $query
                        ->where('released_at', '<', $from)
                        ->where('datetime', '=', $latestPriorObservation)))
                ->orderBy('code')
                ->orderBy('datetime')
                ->orderBy('released_at')
                ->orderBy('id')])
            ->orderBy('id')
            ->get();

        $entities = [];
        foreach ($fundamentals as $fundamental) {
            $observations = [];
            foreach ($fundamental->contents as $content) {
                $observations[] = new FundamentalObservation(
                    code: $content->code,
                    name: $content->name,
                    datetime: \DateTimeImmutable::createFromInterface($content->datetime),
                    releasedAt: $content->released_at === null
                        ? null
                        : \DateTimeImmutable::createFromInterface($content->released_at),
                    value: $content->value,
                );
            }

            $entities[(int) $fundamental->id] = new FundamentalEntityData(
                fundamentalId: (int) $fundamental->id,
                name: $fundamental->name,
                category: $fundamental->category,
                description: $fundamental->description,
                observations: $observations,
            );
        }

        return new FundamentalDataCollection($entities);
    }

    /** @return array{name: string, description: ?string, category: ?string} */
    private function attributesFrom(FundamentalDefinition $definition): array
    {
        return [
            'name' => $definition->name,
            'description' => $definition->description,
            'category' => $definition->category,
        ];
    }

    /**
     * @return array{code: string, name: string, value: array<array-key, mixed>|bool|float|int|string|null, datetime: string, released_at: ?string}
     */
    private function contentAttributesFrom(FundamentalContentDefinition $definition): array
    {
        return [
            'code' => $definition->code,
            'name' => $definition->name,
            'value' => $definition->value,
            'datetime' => $definition->datetime->format('Y-m-d H:i:s'),
            'released_at' => $definition->releasedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
