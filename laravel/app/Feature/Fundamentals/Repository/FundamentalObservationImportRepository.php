<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Repository;

use Illuminate\Support\Facades\DB;

class FundamentalObservationImportRepository
{
    /**
     * @param  list<array{code: string, name: string, datetime: string, released_at: ?string, value: mixed}>  $observations
     * @return array{imported: int, inserted: int, updated: int}
     */
    public function import(int $fundamentalId, array $observations): array
    {
        return DB::transaction(function () use ($fundamentalId, $observations): array {
            $inserted = 0;
            $updated = 0;

            foreach (array_chunk($observations, 100) as $chunk) {
                $codes = array_values(array_unique(array_column($chunk, 'code')));
                $dates = array_values(array_unique(array_column($chunk, 'datetime')));
                $existing = DB::table('fundamental_contents')
                    ->where('fundamental_id', $fundamentalId)
                    ->whereIn('code', $codes)
                    ->whereIn('datetime', $dates)
                    ->get(['code', 'datetime'])
                    ->keyBy(fn (object $row): string => $row->code.'|'.$row->datetime);

                $timestamp = now()->format('Y-m-d H:i:s');
                $rows = [];
                foreach ($chunk as $observation) {
                    $key = $observation['code'].'|'.$observation['datetime'];
                    if ($existing->has($key)) {
                        $updated++;
                    } else {
                        $inserted++;
                    }

                    $rows[] = [
                        'fundamental_id' => $fundamentalId,
                        ...$observation,
                        'value' => json_encode(
                            $observation['value'],
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                        ),
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                DB::table('fundamental_contents')->upsert(
                    $rows,
                    ['fundamental_id', 'code', 'datetime'],
                    ['name', 'value', 'released_at', 'updated_at'],
                );
            }

            return [
                'imported' => count($observations),
                'inserted' => $inserted,
                'updated' => $updated,
            ];
        });
    }
}
