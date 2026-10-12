<?php

declare(strict_types=1);

namespace App\Workbench\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BacktestResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'stress_mode' => ['nullable', 'in:adverse,favorable,combined'],
            'adverse_stress' => ['required_with:stress_mode', 'numeric', 'between:0,1'],
            'favorable_stress' => ['required_with:stress_mode', 'numeric', 'between:0,1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];

        if ($this->boolean('monte_carlo')) {
            $rules += [
                'monte_carlo' => ['accepted'],
                'simulation_count' => ['required', 'integer', 'between:1,10000'],
                'trades_per_simulation' => ['nullable', 'integer', 'between:1,10000'],
                'method' => ['required', 'in:shuffle,bootstrap'],
                'mc_initial_capital' => ['required', 'numeric', 'gt:0'],
                'random_seed' => ['nullable', 'integer', 'between:0,2147483647'],
                'risk_model' => ['required', 'in:fixed_monetary_pl,compounded_return'],
                'drawdown_threshold' => ['required', 'numeric', 'between:0,100'],
            ];
        }

        return $rules;
    }

    public function clusterCount(): ?int
    {
        $requested = $this->query('clusters', 'full');
        if ($requested === 'full') {
            return null;
        }

        $clusterCount = filter_var($requested, FILTER_VALIDATE_INT);

        return $clusterCount !== false && $clusterCount >= 2 && $clusterCount <= 10
            ? $clusterCount
            : null;
    }

    public function selectedCluster(int $clusterCount): int
    {
        $selected = filter_var($this->query('cluster', 1), FILTER_VALIDATE_INT);

        return $selected !== false && $selected >= 1 && $selected <= $clusterCount
            ? $selected
            : 1;
    }
}
