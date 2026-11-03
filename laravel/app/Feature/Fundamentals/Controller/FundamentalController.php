<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Controller;

use App\Feature\Fundamentals\Action\CreateFundamentalAction;
use App\Feature\Fundamentals\Action\DeleteFundamentalAction;
use App\Feature\Fundamentals\Action\ListFundamentalsAction;
use App\Feature\Fundamentals\Action\LoadFundamentalFormPageAction;
use App\Feature\Fundamentals\Action\UpdateFundamentalAction;
use App\Feature\Fundamentals\Action\ViewFundamentalAction;
use App\Feature\Fundamentals\Data\FundamentalDefinition;
use App\Feature\Fundamentals\Request\FundamentalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FundamentalController
{
    public function index(ListFundamentalsAction $action): View
    {
        return view('workbench.fundamentals.index', ['fundamentals' => $action->execute()]);
    }

    public function create(LoadFundamentalFormPageAction $loadForm): View
    {
        return view('workbench.fundamentals.form', $loadForm->execute());
    }

    public function store(FundamentalRequest $request, CreateFundamentalAction $action): RedirectResponse
    {
        $fundamental = $action->execute($this->definitionFrom($request->validated()));

        return redirect()->route('workbench.fundamentals.show', $fundamental)
            ->with('status', 'Fundamental created successfully.');
    }

    public function show(int $fundamental, ViewFundamentalAction $action): View
    {
        return view('workbench.fundamentals.show', $action->execute($fundamental));
    }

    public function edit(int $fundamental, LoadFundamentalFormPageAction $loadForm): View
    {
        return view('workbench.fundamentals.form', $loadForm->execute($fundamental));
    }

    public function update(
        FundamentalRequest $request,
        int $fundamental,
        UpdateFundamentalAction $action,
    ): RedirectResponse {
        $action->execute($fundamental, $this->definitionFrom($request->validated()));

        return redirect()->route('workbench.fundamentals.show', $fundamental)
            ->with('status', 'Fundamental updated successfully.');
    }

    public function destroy(int $fundamental, DeleteFundamentalAction $action): RedirectResponse
    {
        $action->execute($fundamental);

        return redirect()->route('workbench.fundamentals.index')
            ->with('status', 'Fundamental deleted successfully.');
    }

    /**
     * @param  array{name: string, description?: string|null, category?: string|null, pair_ids_present?: string, pair_ids?: list<int|string>}  $validated
     */
    private function definitionFrom(array $validated): FundamentalDefinition
    {
        $pairIds = null;
        if (array_key_exists('pair_ids_present', $validated) || array_key_exists('pair_ids', $validated)) {
            $pairIds = array_map('intval', $validated['pair_ids'] ?? []);
        }

        return new FundamentalDefinition(
            name: $validated['name'],
            description: $validated['description'] ?? null,
            category: $validated['category'] ?? null,
            pairIds: $pairIds,
        );
    }
}
