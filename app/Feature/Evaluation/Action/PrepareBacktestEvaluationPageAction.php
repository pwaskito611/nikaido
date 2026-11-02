<?php

declare(strict_types=1);

namespace App\Feature\Evaluation\Action;

use App\Feature\Evaluation\Data\Analysis\BacktestAnalysisDecision;
use App\Feature\Evaluation\Data\Analysis\BacktestAnalysisInput;
use App\Feature\Evaluation\Data\Analysis\BacktestAnalysisOrder;
use App\Feature\Evaluation\Data\BacktestResultsAnalysis;
use App\Feature\Evaluation\Data\FluctuationStressTestResult;
use App\Feature\Evaluation\Data\MonteCarloSimulationResult;
use App\Feature\Evaluation\Request\BacktestResultsRequest;
use Exact\Collection\Map;
use Exact\Collection\Vec;
use Exact\Query\Query;
use Illuminate\Pagination\LengthAwarePaginator;

class PrepareBacktestEvaluationPageAction
{
    public function __construct(
        private readonly AnalyzeBacktestResultsAction $analyze,
    ) {}

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public function execute(
        BacktestResultsRequest $request,
        array $result,
        string $analysisAction,
        string $resultsTitle,
        string $resultsEyebrow,
        string $returnUrl,
        ?int $successfulPairCount = null,
    ): array {
        $options = $request->toAnalysisOptions();
        $analysis = $this->analyze->execute(
            BacktestAnalysisInput::fromPersistedResult($result, $options->stressParameter->isSome()),
            $options,
        );
        $clusterCount = $analysis->clusterCount->getOrNull();
        $orderHistory = Vec::fromArray($result['order_history']);
        $tradeCostTotals = Query::from($orderHistory)
            ->flatMap(
                static fn (array $order): Vec => Vec::fromArray(
                    Map::fromArray($order['trade_costs'] ?? [])->entries(),
                )->map(static fn (array $entry): array => [$entry[0], (float) $entry[1]]),
            )
            ->groupFold(
                static fn (array $entry): string => $entry[0],
                0.0,
                static fn (float $total, array $entry): float => $total + $entry[1],
            );
        $tradeCostConfiguration = $result['execution_config']['trade_costs'] ?? ['enabled' => false, 'components' => []];
        $modeledCostLabels = Vec::fromArray(Map::fromArray($tradeCostConfiguration['components'] ?? [])
            ->filter(
                static fn (array $component): bool => $tradeCostConfiguration['enabled']
                    && ($component['enabled'] ?? false),
            )
            ->keys()
        )
            ->map(static fn (string $component): string => str_replace('_', ' ', $component))
            ->toArray();

        return [
            'backtest' => $result,
            'executionConfig' => $result['execution_config'] ?? null,
            'tradeCostConfig' => $tradeCostConfiguration,
            'openedOrderCount' => $orderHistory
                ->filter(static fn (array $order): bool => empty($order['portfolio_entry_rejected']))
                ->count(),
            'closedOrderCount' => $orderHistory
                ->filter(static fn (array $order): bool => ($order['close_date'] ?? null) !== null)
                ->count(),
            'tradeCostTotals' => $tradeCostTotals->toArray(),
            'totalAdditionalTradeCosts' => Vec::fromArray($tradeCostTotals->values())
                ->foldLeft(0.0, static fn (float $total, float $amount): float => $total + $amount),
            'modeledCostLabels' => implode(', ', $modeledCostLabels),
            'orders' => $analysis->orders
                ->map(static fn (BacktestAnalysisOrder $order): array => $order->toPresentationArray())
                ->toArray(),
            'decisions' => $this->paginateDecisions($request, $analysis),
            'clusterCount' => $clusterCount,
            'selectedCluster' => $analysis->selectedCluster->getOrNull(),
            'clusterOptions' => $clusterCount === null ? [] : range(1, $clusterCount),
            'clusterAnalysis' => $analysis->clusterAnalysis->toArray(),
            'capitalChart' => $analysis->capitalChart->toArray(),
            'profitFactor' => $analysis->profitFactor,
            'closedTradeCount' => $analysis->closedTradeCount,
            'averageDrawdownPercent' => $analysis->averageDrawdownPercent,
            'maximumDrawdownPercent' => $analysis->maximumDrawdownPercent,
            'medianDrawdownPercent' => $analysis->medianDrawdownPercent,
            'stressTest' => $analysis->stressTest->map(
                static fn (FluctuationStressTestResult $stressTest): array => $stressTest->toArray(),
            )->getOrNull(),
            'monteCarlo' => $analysis->monteCarlo->map(
                static fn (MonteCarloSimulationResult $monteCarlo): array => $monteCarlo->toArray(),
            )->getOrNull(),
            'analysisAction' => $analysisAction,
            'resultsTitle' => $resultsTitle,
            'resultsEyebrow' => $resultsEyebrow,
            'returnUrl' => $returnUrl,
            'successfulPairCount' => $successfulPairCount,
        ];
    }

    private function paginateDecisions(
        BacktestResultsRequest $request,
        BacktestResultsAnalysis $analysis,
    ): LengthAwarePaginator {
        $page = (int) ($request->validated('page') ?? 1);
        $decisions = $analysis->decisions
            ->map(static fn (BacktestAnalysisDecision $decision): array => $decision->toPresentationArray())
            ->toArray();

        return new LengthAwarePaginator(
            array_slice($decisions, ($page - 1) * 50, 50),
            count($decisions),
            50,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
