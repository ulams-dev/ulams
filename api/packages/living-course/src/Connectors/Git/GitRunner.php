<?php

namespace Ulams\LivingCourse\Connectors\Git;

use Ulams\LivingCourse\Connectors\ConnectorException;

/**
 * Runs the `git` binary with an empty environment of its own: no inherited variables (the PHP process
 * holds database passwords and keys), no prompts, no user or system configuration, no hooks. Output is
 * capped, the run has a deadline and an optional watcher can stop it (the size of a clone).
 */
final class GitRunner
{
    /** @param array<string,string> $env extra variables (the access header travels here, never in arguments) */
    public function __construct(private readonly string $binary = 'git', private readonly array $env = [])
    {
    }

    public static function available(string $binary = 'git'): bool
    {
        $process = @proc_open([$binary, '--version'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['PATH' => self::path()]);
        if (!is_resource($process)) {
            return false;
        }
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process) === 0;
    }

    /**
     * @param string[] $args arguments after `git`
     * @param (callable():?string)|null $watch called about every 200 ms; a returned message stops the run and becomes the exception
     * @return array{code:int,stdout:string,stderr:string}
     * @throws ConnectorException on a timeout, a stop by the watcher, an output over the cap or a binary that cannot start
     */
    public function run(array $args, ?string $cwd, string $home, int $timeout, int $maxOutput = 4194304, ?callable $watch = null): array
    {
        $env = $this->env + [
            'PATH' => self::path(),
            'HOME' => $home,
            'XDG_CONFIG_HOME' => $home,
            'GIT_CONFIG_NOSYSTEM' => '1',
            'GIT_CONFIG_GLOBAL' => '/dev/null',
            'GIT_TERMINAL_PROMPT' => '0',
            'GIT_ASKPASS' => '/bin/false',
            'GIT_OPTIONAL_LOCKS' => '0',
            'GIT_PROTOCOL_FROM_USER' => '0',
            'LC_ALL' => 'C',
        ];
        $process = @proc_open([$this->binary, ...$args], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env);
        if (!is_resource($process)) {
            throw new ConnectorException('The git program could not be started on this server.');
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = '';
        $err = '';
        $deadline = microtime(true) + $timeout;
        $nextWatch = 0.0;
        $stop = null;
        while (true) {
            $status = proc_get_status($process);
            $read = [$pipes[1], $pipes[2]];
            $write = $except = null;
            if (@stream_select($read, $write, $except, 0, 100000) > 0) {
                foreach ($read as $stream) {
                    $chunk = (string) fread($stream, 65536);
                    if ($stream === $pipes[1]) {
                        $out .= $chunk;
                    } else {
                        $err .= substr($chunk, 0, 65536);
                    }
                }
            }
            if (strlen($out) > $maxOutput) {
                $stop = 'git returned more output than expected.';
            } elseif (microtime(true) > $deadline) {
                $stop = sprintf('The repository took longer than %d seconds to read.', $timeout);
            } elseif ($watch !== null && microtime(true) >= $nextWatch) {
                $nextWatch = microtime(true) + 0.2;
                $stop = $watch();
            }
            if ($stop !== null) {
                self::kill($process, $pipes);
                throw new ConnectorException($stop);
            }
            if (!$status['running']) {
                $out .= (string) stream_get_contents($pipes[1]);
                $err .= substr((string) stream_get_contents($pipes[2]), 0, 65536);
                break;
            }
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = $status['exitcode'];
        $closed = proc_close($process);

        return ['code' => $code >= 0 ? $code : $closed, 'stdout' => $out, 'stderr' => $err];
    }

    private static function path(): string
    {
        return '/usr/local/bin:/usr/bin:/bin:/opt/homebrew/bin';
    }

    /** @param resource $process @param array<int,resource> $pipes */
    private static function kill($process, array $pipes): void
    {
        @proc_terminate($process, 9);
        foreach ([1, 2] as $n) {
            if (is_resource($pipes[$n] ?? null)) {
                fclose($pipes[$n]);
            }
        }
        @proc_close($process);
    }
}
