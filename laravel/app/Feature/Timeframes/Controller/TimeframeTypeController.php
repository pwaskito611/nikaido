<?php

declare(strict_types=1);

namespace App\Feature\Timeframes\Controller;

use App\Feature\Timeframes\Model\TimeframeType;
use App\Feature\Timeframes\Action\CreateTimeframeTypeAction;
use App\Feature\Timeframes\Action\DeleteTimeframeTypeAction;
use App\Feature\Timeframes\Action\ListTimeframeTypesAction;
use App\Feature\Timeframes\Action\UpdateTimeframeTypeAction;
use App\Feature\Timeframes\Action\ViewTimeframeTypeAction;
use App\Feature\Timeframes\Request\TimeframeTypeRequest;
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
