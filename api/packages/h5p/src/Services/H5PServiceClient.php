<?php

namespace Ulams\H5P\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Ulams\H5P\Exceptions\H5PServiceException;
use Ulams\H5P\Services\Contracts\H5PServiceClientContract;

class H5PServiceClient implements H5PServiceClientContract
{
    public function upload($file): int
    {
        [$path, $name] = $this->resolveFile($file);
        $stream = fopen($path, 'r');
        if ($stream === false) {
            throw new H5PServiceException("Cannot read H5P package: {$path}");
        }

        try {
            $response = $this->send(fn () => $this->request()
                ->attach('h5p_file', $stream, $name)
                ->post($this->url('contents/upload')));
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $contentId = $response->json('data.contentId');
        if ($contentId === null || !is_numeric($contentId)) {
            throw new H5PServiceException('H5P service did not return a content id.', $response->status());
        }

        return (int) $contentId;
    }

    public function create(string $library, array $params, array $metadata): int
    {
        $response = $this->send(fn () => $this->request()->post($this->url('contents'), $this->saveBody($library, $params, $metadata)));
        $contentId = $response->json('data.contentId');
        if ($contentId === null || !is_numeric($contentId)) {
            throw new H5PServiceException('H5P service did not return a content id.', $response->status());
        }

        return (int) $contentId;
    }

    public function update(int $id, string $library, array $params, array $metadata): void
    {
        $this->send(fn () => $this->request()->patch($this->url("contents/{$id}"), $this->saveBody($library, $params, $metadata)));
    }

    public function libraries(): array
    {
        $rows = $this->send(fn () => $this->request()->timeout(10)->get($this->url('libraries')))->json();
        $rows = is_array($rows) && isset($rows['data']) && is_array($rows['data']) ? $rows['data'] : (is_array($rows) ? $rows : []);
        $found = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['machineName'], $row['majorVersion'], $row['minorVersion'])) {
                continue;
            }
            $name = (string) $row['machineName'];
            $version = [(int) $row['majorVersion'], (int) $row['minorVersion']];
            if (!isset($found[$name]) || $version > $found[$name][0]) {
                $found[$name] = [$version, "{$name} {$version[0]}.{$version[1]}"];
            }
        }

        return array_map(fn (array $entry) => $entry[1], $found);
    }

    /** @return array{library:string,params:array{params:array,metadata:array}} */
    private function saveBody(string $library, array $params, array $metadata): array
    {
        return ['library' => $library, 'params' => ['params' => $params, 'metadata' => $metadata + ['language' => 'en', 'license' => 'U']]];
    }

    public function download(int $id): string
    {
        $target = tempnam(sys_get_temp_dir(), 'h5p-export-');
        $path = $target . '.h5p';
        rename($target, $path);

        try {
            $response = $this->send(fn () => $this->request()
                ->withOptions(['sink' => $path])
                ->accept('application/zip')
                ->get($this->url("contents/{$id}/download")));
            clearstatcache(true, $path);
            // Http::fake() responses bypass Guzzle's sink: write the body ourselves.
            if (!filesize($path)) {
                file_put_contents($path, $response->body());
            }
        } catch (H5PServiceException $e) {
            @unlink($path);
            throw $e;
        }

        return $path;
    }

    public function delete(int $id): bool
    {
        try {
            $this->send(fn () => $this->request()->delete($this->url("contents/{$id}")));
        } catch (H5PServiceException $e) {
            if ($e->getStatus() === 404) {
                return false;
            }
            throw $e;
        }

        return true;
    }

    public function show(int $id): array
    {
        return $this->send(fn () => $this->request()->get($this->url("contents/{$id}")))->json('data') ?? [];
    }

    public function deleteOrphans(): array
    {
        $data = $this->send(fn () => $this->request()->post($this->url('contents/orphans/delete')))->json('data') ?? [];

        return [
            'contentIds' => array_map('strval', (array) ($data['contentIds'] ?? [])),
            'files' => (int) ($data['files'] ?? 0),
        ];
    }

    private function request(): PendingRequest
    {
        $request = Http::acceptJson()->timeout((int) config('h5p.timeout', 300));
        $token = config('h5p.internal_token');
        if ($token) {
            $request = $request->withHeaders(['X-Internal-Token' => $token]);
        }
        // The H5P service is multi-tenant and picks the tenant (database, bucket) from the forwarded host.
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($host) {
            $request = $request->withHeaders(['X-Forwarded-Host' => $host]);
        }

        return $request;
    }

    private function send(callable $call): Response
    {
        try {
            /** @var Response $response */
            $response = $call();
        } catch (ConnectionException $e) {
            throw new H5PServiceException('H5P service is unreachable: ' . $e->getMessage(), null, $e);
        }

        if ($response->failed()) {
            $message = $response->json('message') ?: Str::limit($response->body(), 200);
            throw new H5PServiceException("H5P service error ({$response->status()}): {$message}", $response->status());
        }

        return $response;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('h5p.service_url'), '/') . '/h5p/' . ltrim($path, '/');
    }

    /**
     * @param UploadedFile|string $file
     * @return array{0: string, 1: string} [local path, file name ending in .h5p]
     */
    private function resolveFile($file): array
    {
        if ($file instanceof UploadedFile) {
            $path = $file->getRealPath() ?: $file->getPathname();
            $name = $file->getClientOriginalName() ?: $file->getFilename();
        } else {
            $path = (string) $file;
            $name = basename($path);
        }

        if (!is_file($path)) {
            throw new H5PServiceException("H5P package not found: {$path}");
        }

        // The service only accepts names ending in .h5p.
        if (!Str::endsWith(Str::lower($name), '.h5p')) {
            $name = 'package.h5p';
        }

        return [$path, $name];
    }
}
