<?php

declare(strict_types=1);

namespace App\Feature\News\Action;

use App\Feature\News\Model\NewsItem;
use App\Feature\News\Repository\NewsRepository;

class FindNewsAction
{
    public function __construct(private readonly NewsRepository $news) {}

    public function execute(int $id): NewsItem
    {
        return $this->news->find($id);
    }
}
