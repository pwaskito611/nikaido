<?php

declare(strict_types=1);

namespace App\Feature\News\Action;

use App\Feature\News\Repository\NewsRepository;

class DeleteNewsAction
{
    public function __construct(private readonly NewsRepository $news) {}

    public function execute(int $id): void
    {
        $this->news->delete($id);
    }
}
