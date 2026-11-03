<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Action;

use App\Feature\Fundamentals\Model\Fundamental;
use App\Feature\Fundamentals\Model\FundamentalContent;
use App\Feature\Fundamentals\Repository\FundamentalRepository;

class LoadFundamentalContentFormPageAction
{
    public function __construct(
        private readonly FundamentalRepository $fundamentals,
    ) {}

    /** @return array{fundamental: Fundamental, content: FundamentalContent} */
    public function execute(int $fundamentalId, ?int $contentId = null): array
    {
        return [
            'fundamental' => $this->fundamentals->findOrFail($fundamentalId),
            'content' => $contentId === null
                ? new FundamentalContent
                : $this->fundamentals->findContentOrFail($fundamentalId, $contentId),
        ];
    }
}
