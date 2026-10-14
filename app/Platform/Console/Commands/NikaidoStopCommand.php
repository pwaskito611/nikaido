<?php

declare(strict_types=1);

namespace App\Platform\Console\Commands;

use App\Platform\Console\NikaidoProcessManager;
use Illuminate\Console\Command;
use RuntimeException;

class NikaidoStopCommand extends Command
{
    protected $signature = 'nikaido:stop';

    protected $description = 'Stop the Nikaido development server and queue worker';

    public function handle(NikaidoProcessManager $processes): int
    {
        try {
            $stopped = $processes->stopAll();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach (['server' => 'Server', 'worker' => 'Queue worker'] as $name => $label) {
            $this->line($stopped[$name]
                ? "{$label} stopped."
                : "{$label} was not running.");
        }

        return self::SUCCESS;
    }
}
