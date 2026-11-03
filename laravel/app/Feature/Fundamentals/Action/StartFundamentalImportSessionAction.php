<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Action;

use App\Feature\Fundamentals\Model\FundamentalImportSession;
use App\Feature\Fundamentals\Model\StartedFundamentalImportSession;
use App\Feature\Fundamentals\Repository\FundamentalImportSessionRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StartFundamentalImportSessionAction
{
    public function __construct(
        private readonly FundamentalImportSessionRepository $sessions,
    ) {}

    public function execute(int $fundamentalId): StartedFundamentalImportSession
    {
        $token = Str::random(64);
        try {
            $session = DB::transaction(function () use ($fundamentalId, $token): FundamentalImportSession {
                $this->sessions->lockFundamental($fundamentalId);

                if ($this->sessions->hasActiveForFundamental($fundamentalId)) {
                    throw ValidationException::withMessages([
                        'fundamental' => 'An incoming import session is already active for this fundamental.',
                    ]);
                }

                return $this->sessions->createActive($fundamentalId, hash('sha256', $token));
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages([
                'fundamental' => 'An incoming import session is already active for this fundamental.',
            ]);
        }

        return new StartedFundamentalImportSession($session, $token);
    }
}
