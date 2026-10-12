<?php

declare(strict_types=1);

namespace App\Console;

use RuntimeException;
use Symfony\Component\Process\Process;

class NikaidoProcessManager
{
    private const PROCESSES = [
        'server' => ['serve'],
        'worker' => ['queue:work'],
    ];

    public function startServer(string $host, int $port): int
    {
        $existingPid = $this->readPid('server');
        if ($existingPid !== null && $this->isManagedProcess($existingPid, 'server')) {
            return $existingPid;
        }

        $pid = $this->start('server', ['serve', '--host='.$host, '--port='.$port]);
        $this->waitForServer($pid, $host, $port);

        return $pid;
    }

    /**
     * @return array{server: int, worker: int}
     */
    public function startAll(string $host, int $port): array
    {
        $serverWasRunning = $this->isManagedProcess($this->readPid('server') ?? 0, 'server');
        $workerWasRunning = $this->isManagedProcess($this->readPid('worker') ?? 0, 'worker');

        try {
            return [
                'server' => $this->startServer($host, $port),
                'worker' => $this->startWorker(),
            ];
        } catch (RuntimeException $exception) {
            $rollbackErrors = [];
            foreach (['worker' => $workerWasRunning, 'server' => $serverWasRunning] as $name => $wasRunning) {
                if ($wasRunning) {
                    continue;
                }

                try {
                    $this->stop($name);
                } catch (RuntimeException $rollbackException) {
                    $rollbackErrors[] = $rollbackException->getMessage();
                }
            }

            $message = $exception->getMessage();
            if ($rollbackErrors !== []) {
                $message .= ' Rollback issue: '.implode(' ', $rollbackErrors);
            }

            throw new RuntimeException($message, previous: $exception);
        }
    }

    public function serverAddress(int $pid): string
    {
        $commandLine = @file_get_contents("/proc/{$pid}/cmdline");
        if (! is_string($commandLine) || $commandLine === '') {
            throw new RuntimeException("The Nikaido development server (PID {$pid}) is no longer running.");
        }
        $arguments = explode("\0", $commandLine);
        $host = '127.0.0.1';
        $port = '8000';

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--host=')) {
                $host = substr($argument, strlen('--host='));
            } elseif (str_starts_with($argument, '--port=')) {
                $port = substr($argument, strlen('--port='));
            }
        }

        return "http://{$host}:{$port}";
    }

    public function startWorker(): int
    {
        return $this->start('worker', ['queue:work', '--tries=1', '--timeout=0']);
    }

    /**
     * @return array{server: bool, worker: bool}
     */
    public function stopAll(): array
    {
        $stopped = [];
        $errors = [];
        foreach (['worker', 'server'] as $name) {
            try {
                $stopped[$name] = $this->stop($name);
            } catch (RuntimeException $exception) {
                $stopped[$name] = false;
                $errors[] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            throw new RuntimeException(implode(' ', $errors));
        }

        return $stopped;
    }

    private function start(string $name, array $arguments): int
    {
        $this->assertSupportedPlatform();
        $this->ensureRuntimeDirectory();

        $existingPid = $this->readPid($name);
        if ($existingPid !== null && $this->isManagedProcess($existingPid, $name)) {
            return $existingPid;
        }
        $this->removePidFile($name);

        $logPath = $this->logPath($name);
        $command = array_merge([PHP_BINARY, $this->artisanPath()], $arguments);
        $shellCommand = 'nohup '.implode(' ', array_map('escapeshellarg', $command))
            .' >> '.escapeshellarg($logPath).' 2>&1 < /dev/null & echo $!';
        $process = Process::fromShellCommandline($shellCommand, base_path(), null, null, 5);
        $process->run();

        if (! $process->isSuccessful() || ! preg_match('/^\d+$/', trim($process->getOutput()))) {
            throw new RuntimeException("Unable to start the Nikaido {$name}. Check that nohup is available.");
        }

        $pid = (int) trim($process->getOutput());
        for ($attempt = 0; $attempt < 10; $attempt++) {
            if ($this->isManagedProcess($pid, $name)) {
                $this->writePid($name, $pid);

                return $pid;
            }
            usleep(100_000);
        }

        throw new RuntimeException("The Nikaido {$name} exited during startup. See {$logPath} for details.");
    }

    private function stop(string $name): bool
    {
        $this->assertSupportedPlatform();
        $pid = $this->readPid($name);
        if ($pid === null) {
            return false;
        }

        if (! $this->isManagedProcess($pid, $name)) {
            $this->removePidFile($name);

            return false;
        }

        if (! posix_kill($pid, SIGTERM)) {
            throw new RuntimeException("Unable to send a stop signal to the Nikaido {$name} (PID {$pid}).");
        }

        for ($attempt = 0; $attempt < 50; $attempt++) {
            if (! $this->isManagedProcess($pid, $name)) {
                $this->removePidFile($name);

                return true;
            }
            usleep(100_000);
        }

        if ($this->isManagedProcess($pid, $name) && ! posix_kill($pid, SIGKILL)) {
            throw new RuntimeException("Unable to force-stop the Nikaido {$name} (PID {$pid}).");
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            if (! $this->isManagedProcess($pid, $name)) {
                $this->removePidFile($name);

                return true;
            }
            usleep(100_000);
        }

        throw new RuntimeException("The Nikaido {$name} (PID {$pid}) did not stop after receiving a force-stop signal.");
    }

    private function isManagedProcess(int $pid, string $name): bool
    {
        if ($pid <= 1) {
            return false;
        }

        $commandLine = @file_get_contents("/proc/{$pid}/cmdline");
        if (! is_string($commandLine) || $commandLine === '') {
            return false;
        }

        $arguments = explode("\0", $commandLine);

        return in_array($this->artisanPath(), $arguments, true)
            && in_array(self::PROCESSES[$name][0], $arguments, true);
    }

    private function waitForServer(int $pid, string $host, int $port): void
    {
        $connectHost = in_array($host, ['0.0.0.0', '::'], true) ? '127.0.0.1' : trim($host, '[]');
        $target = str_contains($connectHost, ':')
            ? "tcp://[{$connectHost}]:{$port}"
            : "tcp://{$connectHost}:{$port}";

        for ($attempt = 0; $attempt < 50; $attempt++) {
            if (! $this->isManagedProcess($pid, 'server')) {
                throw new RuntimeException("The Nikaido development server exited during startup. See {$this->logPath('server')} for details.");
            }

            $socket = @stream_socket_client($target, $errorCode, $errorMessage, 0.1);
            if (is_resource($socket)) {
                fclose($socket);

                return;
            }
            usleep(100_000);
        }

        throw new RuntimeException("The Nikaido development server did not become reachable at http://{$host}:{$port} within 5 seconds.");
    }

    private function readPid(string $name): ?int
    {
        $path = $this->pidPath($name);
        if (! is_file($path)) {
            return null;
        }

        $value = file_get_contents($path);
        if (! is_string($value) || ! ctype_digit(trim($value))) {
            $this->removePidFile($name);

            return null;
        }

        $pid = (int) trim($value);

        return $pid > 1 ? $pid : null;
    }

    private function writePid(string $name, int $pid): void
    {
        if (file_put_contents($this->pidPath($name), (string) $pid, LOCK_EX) === false) {
            if ($this->isManagedProcess($pid, $name)) {
                posix_kill($pid, SIGTERM);
            }

            throw new RuntimeException("Unable to save the Nikaido {$name} process ID.");
        }
    }

    private function removePidFile(string $name): void
    {
        $path = $this->pidPath($name);
        if (is_file($path) && ! unlink($path)) {
            throw new RuntimeException("Unable to remove the Nikaido {$name} process ID file.");
        }
    }

    private function ensureRuntimeDirectory(): void
    {
        foreach ([$this->runtimeDirectory(), dirname($this->logPath('server'))] as $directory) {
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException("Unable to create the Nikaido runtime directory at {$directory}.");
            }
        }
    }

    private function assertSupportedPlatform(): void
    {
        if (PHP_OS_FAMILY !== 'Linux' || ! function_exists('posix_kill')) {
            throw new RuntimeException('Nikaido start/stop commands require Linux and the PHP POSIX extension.');
        }
    }

    private function runtimeDirectory(): string
    {
        return storage_path('framework/nikaido-processes');
    }

    private function pidPath(string $name): string
    {
        return $this->runtimeDirectory().'/'.$name.'.pid';
    }

    private function logPath(string $name): string
    {
        return storage_path('logs/nikaido-'.$name.'.log');
    }

    private function artisanPath(): string
    {
        return base_path('artisan');
    }
}
