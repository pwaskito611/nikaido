<?php

declare(strict_types=1);

namespace App\Workbench\Controllers;

use App\Engine\Models\TimeframeType;
use App\Workbench\Actions\TimeframeTypes\CreateTimeframeTypeAction;
use App\Workbench\Actions\TimeframeTypes\DeleteTimeframeTypeAction;
use App\Workbench\Actions\TimeframeTypes\ListTimeframeTypesAction;
use App\Workbench\Actions\TimeframeTypes\UpdateTimeframeTypeAction;
use App\Workbench\Actions\TimeframeTypes\ViewTimeframeTypeAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    public function store(Request $request, CreateTimeframeTypeAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:timeframe_type,name'],
        ]);

        $timeframe = $action->execute(trim($validated['name']));

        return redirect()->route('workbench.timeframes.show', $timeframe)
            ->with('status', 'Timeframe berhasil dibuat.');
    }

    public function show(int $timeframe, ViewTimeframeTypeAction $action): View
    {
        return view('workbench.timeframes.show', ['timeframe' => $action->execute($timeframe)]);
    }

    public function edit(int $timeframe, ViewTimeframeTypeAction $action): View
    {
        return view('workbench.timeframes.form', ['timeframe' => $action->execute($timeframe)]);
    }

    public function update(Request $request, int $timeframe, UpdateTimeframeTypeAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('timeframe_type', 'name')->ignore($timeframe),
            ],
        ]);

        $action->execute($timeframe, trim($validated['name']));

        return redirect()->route('workbench.timeframes.show', $timeframe)
            ->with('status', 'Timeframe berhasil diperbarui.');
    }

    public function destroy(int $timeframe, DeleteTimeframeTypeAction $action): RedirectResponse
    {
        $action->execute($timeframe);

        return redirect()->route('workbench.timeframes.index')
            ->with('status', 'Timeframe berhasil dihapus.');
    }
}
