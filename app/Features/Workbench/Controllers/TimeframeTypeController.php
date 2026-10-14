<?php

declare(strict_types=1);

namespace App\Features\Workbench\Controllers;

use App\Features\Engine\Models\TimeframeType;
use App\Features\Workbench\Actions\TimeframeTypes\CreateTimeframeTypeAction;
use App\Features\Workbench\Actions\TimeframeTypes\DeleteTimeframeTypeAction;
use App\Features\Workbench\Actions\TimeframeTypes\ListTimeframeTypesAction;
use App\Features\Workbench\Actions\TimeframeTypes\UpdateTimeframeTypeAction;
use App\Features\Workbench\Actions\TimeframeTypes\ViewTimeframeTypeAction;
use App\Features\Workbench\Requests\TimeframeTypeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TimeframeTypeController
{
    public function index(ListTimeframeTypesAction $action): View
    {
        return view('workbench.timeframes.index', ['timeframes' => $action->execute()]);
    }

    public function create(): View
    {
        return view('workbench.timeframes.form', ['timeframe' => new TimeframeType]);
    }

    public function store(TimeframeTypeRequest $request, CreateTimeframeTypeAction $action): RedirectResponse
    {
        $timeframe = $action->execute($request->validated('name'));

        return redirect()->route('workbench.timeframes.show', $timeframe)
            ->with('status', 'Timeframe created successfully.');
    }

    public function show(int $timeframe, ViewTimeframeTypeAction $action): View
    {
        return view('workbench.timeframes.show', ['timeframe' => $action->execute($timeframe)]);
    }

    public function edit(int $timeframe, ViewTimeframeTypeAction $action): View
    {
        return view('workbench.timeframes.form', ['timeframe' => $action->execute($timeframe)]);
    }

    public function update(TimeframeTypeRequest $request, int $timeframe, UpdateTimeframeTypeAction $action): RedirectResponse
    {
        $action->execute($timeframe, $request->validated('name'));

        return redirect()->route('workbench.timeframes.show', $timeframe)
            ->with('status', 'Timeframe updated successfully.');
    }

    public function destroy(int $timeframe, DeleteTimeframeTypeAction $action): RedirectResponse
    {
        $action->execute($timeframe);

        return redirect()->route('workbench.timeframes.index')
            ->with('status', 'Timeframe deleted successfully.');
    }
}
