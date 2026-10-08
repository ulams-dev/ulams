<?php

namespace Ulams\Jitsi\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Ulams\Jitsi\Exceptions\RecordingUrlNotAllowedException;

/**
 * Downloads a recording from a URL received in a webhook.
 *
 * Network-level guards against server-side request forgery: https only, no redirects, every address the host
 * resolves to must be public (no private, loopback, link-local or reserved ranges) and the connection is pinned
 * to the checked address; the download is capped at jitsi.recording_max_bytes.
 *
 * @return resource a read stream of a temporary file
 */
class FileService
{
    public function getFileFromUrl(string $url)
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? null;

        if (($parts['scheme'] ?? null) !== 'https' || empty($host) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RecordingUrlNotAllowedException();
        }

        $host = trim($host, '[]');
        $port = (int) ($parts['port'] ?? 443);
        $ip = $this->resolvePublicAddress($host);
        $maxBytes = (int) config('jitsi.recording_max_bytes', 2 * 1024 * 1024 * 1024);

        $path = tempnam(sys_get_temp_dir(), 'jitsi-recording-');
        $options = [
            'allow_redirects' => false,
            'sink' => $path,
            'progress' => function ($downloadTotal, $downloaded) use ($maxBytes) {
                if ($downloadTotal > $maxBytes || $downloaded > $maxBytes) {
                    throw new RuntimeException('Recording exceeds the size limit');
                }
            },
        ];
        if (filter_var($host, FILTER_VALIDATE_IP) === false && defined('CURLOPT_RESOLVE')) {
            $pinned = str_contains($ip, ':') ? "[{$ip}]" : $ip;
            $options['curl'] = [CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinned}"]];
        }

        try {
            $response = Http::withOptions($options)
                ->timeout((int) config('jitsi.recording_download_timeout', 600))
                ->get($url);
        } catch (\Throwable $exception) {
            @unlink($path);
            throw new RecordingUrlNotAllowedException(__('Recording could not be downloaded'), 400, $exception);
        }

        if (!$response->successful()) {
            @unlink($path);
            throw new RecordingUrlNotAllowedException(__('Recording could not be downloaded'), 400);
        }

        // Faked responses (tests) do not go through the sink.
        clearstatcache(true, $path);
        if (filesize($path) === 0 && $response->body() !== '') {
            file_put_contents($path, $response->body());
            clearstatcache(true, $path);
        }

        if (filesize($path) > $maxBytes) {
            @unlink($path);
            throw new RecordingUrlNotAllowedException(__('Recording exceeds the size limit'));
        }

        $stream = fopen($path, 'r');
        // The file disappears once the stream is closed (POSIX).
        @unlink($path);

        return $stream;
    }

    /**
     * Returns an address of the host after checking that all its addresses are public.
     */
    protected function resolvePublicAddress(string $host): string
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $addresses = [$host];
        } else {
            $addresses = gethostbynamel($host) ?: [];
            foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (!empty($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        if (empty($addresses)) {
            throw new RecordingUrlNotAllowedException();
        }

        foreach ($addresses as $address) {
            if (!self::isPublicAddress($address)) {
                throw new RecordingUrlNotAllowedException();
            }
        }

        return $addresses[0];
    }

    public static function isPublicAddress(string $address): bool
    {
        // IPv4-mapped IPv6 (::ffff:10.0.0.1) is checked as IPv4.
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $address, $matches)) {
            $address = $matches[1];
        }

        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
