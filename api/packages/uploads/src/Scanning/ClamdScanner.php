<?php

namespace Ulams\Uploads\Scanning;

use Closure;
use Illuminate\Support\Facades\Log;
use Ulams\Uploads\Exceptions\UploadRejected;

/**
 * Streams a file to clamd with the INSTREAM command (ClamAV runs as a separate process,
 * optional compose profile `av`). Chunks are sent as <4-byte big-endian length><data>,
 * terminated by a zero-length chunk; clamd answers `stream: OK` or `stream: <name> FOUND`.
 */
class ClamdScanner implements VirusScannerContract
{
    private const CHUNK = 64 * 1024;

    /** @var Closure(): resource */
    private Closure $connect;

    /**
     * @param (Closure(): resource)|null $connect opens the connection (tests pass a socket pair)
     */
    public function __construct(
        private readonly string $host = 'clamav',
        private readonly int $port = 3310,
        private readonly int $timeout = 60,
        private readonly bool $failClosed = true,
        ?Closure $connect = null,
    ) {
        $this->connect = $connect ?? function () {
            $errno = 0;
            $error = '';
            $socket = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $error, $this->timeout);
            if ($socket === false) {
                throw new \RuntimeException("clamd unreachable: {$error} ({$errno})");
            }
            stream_set_timeout($socket, $this->timeout);

            return $socket;
        };
    }

    public function scan(string $path): void
    {
        try {
            $reply = $this->instream($path);
        } catch (\Throwable $e) {
            Log::warning('[uploads] virus scan failed', ['error' => $e->getMessage()]);
            if ($this->failClosed) {
                throw new UploadRejected('scan_failed', 'The file could not be scanned for viruses; try again later.');
            }

            return;
        }

        if (preg_match('/^stream: (.+) FOUND$/', $reply, $m)) {
            Log::warning('[uploads] infected upload rejected', ['signature' => $m[1]]);
            throw new UploadRejected('infected', 'The file was rejected by the virus scanner (' . $m[1] . ').');
        }
        if ($reply !== 'stream: OK') {
            Log::warning('[uploads] unexpected clamd reply', ['reply' => $reply]);
            if ($this->failClosed) {
                throw new UploadRejected('scan_failed', 'The file could not be scanned for viruses; try again later.');
            }
        }
    }

    private function instream(string $path): string
    {
        $file = fopen($path, 'rb');
        if ($file === false) {
            throw new \RuntimeException('cannot open upload');
        }
        $socket = ($this->connect)();

        try {
            fwrite($socket, "zINSTREAM\0");
            while (!feof($file)) {
                $chunk = (string) fread($file, self::CHUNK);
                if ($chunk === '') {
                    continue;
                }
                fwrite($socket, pack('N', strlen($chunk)) . $chunk);
            }
            fwrite($socket, pack('N', 0));

            $reply = '';
            while (!feof($socket)) {
                $part = fread($socket, 4096);
                if ($part === false || $part === '') {
                    break;
                }
                $reply .= $part;
                if (str_contains($reply, "\0")) {
                    break;
                }
            }

            return trim(str_replace("\0", '', $reply));
        } finally {
            fclose($file);
            fclose($socket);
        }
    }
}
