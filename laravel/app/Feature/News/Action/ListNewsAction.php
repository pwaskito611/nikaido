<?php

declare(strict_types=1);

namespace App\Feature\News\Action;

use App\Feature\News\Repository\NewsRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListNewsAction
{
    public function __construct(private readonly NewsRepository $news) {}

    public function execute(): LengthAwarePaginator
    {
        return $this->news->paginate();
    }
}
