<?php

declare(strict_types=1);

namespace App\Feature\News\Action;

use App\Feature\News\Data\NewsDefinition;
use App\Feature\News\Model\NewsItem;
use App\Feature\News\Repository\NewsRepository;

class CreateNewsAction
{
    public function __construct(private readonly NewsRepository $news) {}

    public function execute(NewsDefinition $definition): NewsItem
    {
        return $this->news->create($definition);
    }
}
