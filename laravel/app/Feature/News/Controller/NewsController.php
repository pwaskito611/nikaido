<?php

declare(strict_types=1);

namespace App\Feature\News\Controller;

use App\Feature\MarketPairs\Repository\MarketPairRepository;
use App\Feature\News\Action\CreateNewsAction;
use App\Feature\News\Action\DeleteNewsAction;
use App\Feature\News\Action\FindNewsAction;
use App\Feature\News\Action\ListNewsAction;
use App\Feature\News\Action\UpdateNewsAction;
use App\Feature\News\Data\NewsDefinition;
use App\Feature\News\Model\NewsItem;
use App\Feature\News\Request\NewsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NewsController
{
    public function index(ListNewsAction $listNews): View
    {
        return view('workbench.news.index', ['newsItems' => $listNews->execute()]);
    }

    public function create(MarketPairRepository $marketPairs): View
    {
        return view('workbench.news.form', [
            'newsItem' => new NewsItem,
            'marketPairs' => $marketPairs->all(),
            'impact' => null,
        ]);
    }

    public function store(NewsRequest $request, CreateNewsAction $createNews): RedirectResponse
    {
        $newsItem = $createNews->execute(NewsDefinition::fromArray($request->validated()));

        return redirect()->route('workbench.news.edit', $newsItem)->with('status', 'News item created.');
    }

    public function edit(int $news, FindNewsAction $findNews, MarketPairRepository $marketPairs): View
    {
        $newsItem = $findNews->execute($news);

        return view('workbench.news.form', [
            'newsItem' => $newsItem,
            'marketPairs' => $marketPairs->all(),
            'impact' => $newsItem->impacts->first(),
        ]);
    }

    public function update(int $news, NewsRequest $request, UpdateNewsAction $updateNews): RedirectResponse
    {
        $newsItem = $updateNews->execute($news, NewsDefinition::fromArray($request->validated()));

        return redirect()->route('workbench.news.edit', $newsItem)->with('status', 'News item updated.');
    }

    public function destroy(int $news, DeleteNewsAction $deleteNews): RedirectResponse
    {
        $deleteNews->execute($news);

        return redirect()->route('workbench.news.index')->with('status', 'News item deleted.');
    }
}
