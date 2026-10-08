<?php

namespace Ulams\Tenancy\Services;

use Dotenv\Dotenv;
use RuntimeException;
use Symfony\Component\Process\Process;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;

/**
 * Runs artisan for a tenant in a child process. An in-process Artisan::call would keep the
 * platform configuration (database, Redis prefix, keys), so tenant work always goes through
 * a fresh `php artisan --domain=<host>`.
 */
class ProcessTenantCommandRunner implements TenantCommandRunnerContract
{
    public function __construct(
        private string $basePath,
        private string $phpBinary = 'php',
        private int $timeout = 900,
    ) {
    }

    public function run(string $host, array $arguments): string
    {
        $process = new Process(
            [$this->phpBinary, $this->basePath . '/artisan', ...$arguments, '--no-interaction', '--domain=' . $host],
            $this->basePath,
            $this->scrubbedEnvironment($host),
            null,
            $this->timeout
        );
        $process->run();

        $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());
        if (!$process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                "`artisan %s --domain=%s` failed (exit %s):\n%s",
                implode(' ', $arguments),
                $host,
                $process->getExitCode(),
                $output
            ));
        }

        return $output;
    }

    /**
     * Dotenv in the child is immutable: any variable inherited from this process (which has
     * the platform `.env` loaded) would win over the tenant file. Unset every key either
     * file defines, plus anything loaded into $_ENV.
     *
     * @return array<string, false>
     */
    public function scrubbedEnvironment(string $host): array
    {
        $keys = array_keys($_ENV);
        foreach (['.env', '.env.' . $host] as $file) {
            $path = $this->basePath . '/' . $file;
            if (is_file($path)) {
                $keys = [...$keys, ...array_keys(Dotenv::parse((string) file_get_contents($path)))];
            }
        }

        $keep = ['PATH', 'HOME', 'USER', 'HOSTNAME', 'TMPDIR', 'LANG', 'TERM'];

        return array_fill_keys(array_diff(array_unique($keys), $keep), false);
    }
}
