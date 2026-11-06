<?php

declare(strict_types=1);

namespace App\Feature\News\Action;

use App\Feature\News\Model\NewsImportSession;
use App\Feature\News\Repository\NewsRepository;

class ReceiveNewsAction
{
    public function __construct(private readonly NewsRepository $news) {}

    /** @param list<array<string,mixed>> $articles */
    public function execute(NewsImportSession $session, array $articles): array
    {
        return $this->news->importForPair((int) $session->market_pair_id, $articles);
    }
}
