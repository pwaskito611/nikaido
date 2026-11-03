<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Controller;

use App\Feature\Fundamentals\Action\CreateFundamentalContentAction;
use App\Feature\Fundamentals\Action\DeleteFundamentalContentAction;
use App\Feature\Fundamentals\Action\LoadFundamentalContentFormPageAction;
use App\Feature\Fundamentals\Action\UpdateFundamentalContentAction;
use App\Feature\Fundamentals\Data\FundamentalContentDefinition;
use App\Feature\Fundamentals\Request\FundamentalContentRequest;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use JsonException;

class FundamentalContentController
{
    public function create(
        int $fundamental,
        LoadFundamentalContentFormPageAction $loadForm,
    ): View {
        return view('workbench.fundamentals.content-form', $loadForm->execute($fundamental));
    }

    public function store(
        FundamentalContentRequest $request,
        int $fundamental,
        CreateFundamentalContentAction $action,
    ): RedirectResponse {
        $action->execute($fundamental, $this->definitionFrom($request->validated()));

        return redirect()->route('workbench.fundamentals.show', $fundamental)
            ->with('status', 'Fundamental observation created successfully.');
    }

    public function edit(
        int $fundamental,
        int $content,
        LoadFundamentalContentFormPageAction $loadForm,
    ): View {
        return view('workbench.fundamentals.content-form', $loadForm->execute($fundamental, $content));
    }

    public function update(
        FundamentalContentRequest $request,
        int $fundamental,
        int $content,
        UpdateFundamentalContentAction $action,
    ): RedirectResponse {
        $action->execute($fundamental, $content, $this->definitionFrom($request->validated()));

        return redirect()->route('workbench.fundamentals.show', $fundamental)
            ->with('status', 'Fundamental observation updated successfully.');
    }

    public function destroy(
        int $fundamental,
        int $content,
        DeleteFundamentalContentAction $action,
    ): RedirectResponse {
        $action->execute($fundamental, $content);

        return redirect()->route('workbench.fundamentals.show', $fundamental)
            ->with('status', 'Fundamental observation deleted successfully.');
    }

    /**
     * @param  array{code: string, name: string, value: string, datetime: string, released_at?: string|null}  $validated
     *
     * @throws JsonException
     */
    private function definitionFrom(array $validated): FundamentalContentDefinition
    {
        return new FundamentalContentDefinition(
            code: $validated['code'],
            name: $validated['name'],
            value: json_decode($validated['value'], true, 512, JSON_THROW_ON_ERROR),
            datetime: new DateTimeImmutable($validated['datetime']),
            releasedAt: isset($validated['released_at']) && $validated['released_at'] !== ''
                ? new DateTimeImmutable($validated['released_at'])
                : null,
        );
    }
}
