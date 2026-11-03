<?php

declare(strict_types=1);

namespace App\Platform\Console\Commands;

use App\Platform\Console\NikaidoProcessManager;
use Illuminate\Console\Command;
use RuntimeException;

class NikaidoStartCommand extends Command
{
    protected $signature = 'nikaido:start
                            {--host=127.0.0.1 : Host address for the development server}
                            {--port=8000 : Port for the development server}';

    protected $description = 'Start the Nikaido development server and queue worker';

    public function handle(NikaidoProcessManager $processes): int
    {
        $host = (string) $this->option('host');
        $port = filter_var($this->option('port'), FILTER_VALIDATE_INT);
        if ($host === '' || $port === false || $port < 1 || $port > 65535) {
            $this->error('A valid host and port between 1 and 65535 are required.');

            return self::FAILURE;
        }

        try {
            $started = $processes->startAll($host, $port);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Nikaido server running at {$processes->serverAddress($started['server'])} (PID {$started['server']}).");
        $this->info("Nikaido queue worker running (PID {$started['worker']}).");
        $this->comment('Logs: storage/logs/nikaido-server.log and storage/logs/nikaido-worker.log');

        return self::SUCCESS;
    }
}
