<?php

namespace Ulams\Core\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Idempotency-Key` for POST, PUT, PATCH and DELETE (ADR 0074, docs/plans/cli.md 6.4), so an agent
 * or a CLI can retry after a timeout without doing the work twice.
 *
 * Scope: host, user, method, route and key. The same key with the same body replays the stored
 * response for 24 hours (`Idempotent-Replayed: true`); the same key with a different body is 422
 * `idempotency_mismatch`; a duplicate arriving while the first is still running is 409
 * `idempotency_in_progress`. Only authenticated requests take part. Responses that say nothing
 * about the work (401, 403, 429, 5xx) and responses over 1 MB are not stored, so the caller can retry.
 */
class Idempotency
{
    public const HEADER = 'Idempotency-Key';

    public const TTL_SECONDS = 86400;

    public const LOCK_SECONDS = 30;

    private const MAX_STORED_BYTES = 1048576;

    private const NOT_STORED = [401, 403, 429];

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->headers->get(self::HEADER);
        if ($key === null || in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }
        if (!is_string($key) || $key === '' || strlen($key) > 255) {
            return $this->error('The Idempotency-Key must be 1 to 255 characters.', 'idempotency_key_invalid', 422);
        }
        $user = $request->user('api') ?? $request->user();
        $route = $request->route();
        if ($user === null || $route === null) {
            return $next($request);
        }

        $base = 'idempotency:' . hash('sha256', implode('|', [
            $request->getHost(), $user->getAuthIdentifier(), $request->getMethod(), $route->uri(), $key,
        ]));
        $hash = $this->bodyHash($request);

        if (($stored = Cache::get($base)) !== null) {
            return $this->replay($stored, $hash);
        }

        $lock = Cache::lock($base . ':lock', self::LOCK_SECONDS);
        if (!$lock->get()) {
            return $this->error('A request with this Idempotency-Key is still being processed.', 'idempotency_in_progress', 409);
        }
        try {
            // another worker may have finished between the first look and the lock
            if (($stored = Cache::get($base)) !== null) {
                return $this->replay($stored, $hash);
            }
            $response = $next($request);
            $this->store($base, $hash, $response);

            return $response;
        } finally {
            $lock->release();
        }
    }

    /** @param array<string,mixed> $stored */
    private function replay(array $stored, string $hash): Response
    {
        if (!hash_equals($stored['hash'], $hash)) {
            return $this->error('This Idempotency-Key was already used with a different request body.', 'idempotency_mismatch', 422);
        }

        return new Response($stored['body'], $stored['status'], $stored['headers'] + ['Idempotent-Replayed' => 'true']);
    }

    private function store(string $base, string $hash, Response $response): void
    {
        $status = $response->getStatusCode();
        $body = $response->getContent();
        if ($status >= 500 || in_array($status, self::NOT_STORED, true) || !is_string($body) || strlen($body) > self::MAX_STORED_BYTES) {
            return;
        }
        $headers = [];
        foreach (['Content-Type', 'Location', 'Content-Disposition'] as $name) {
            if ($response->headers->has($name)) {
                $headers[$name] = $response->headers->get($name);
            }
        }
        Cache::put($base, ['hash' => $hash, 'status' => $status, 'body' => $body, 'headers' => $headers], self::TTL_SECONDS);
    }

    private function bodyHash(Request $request): string
    {
        $files = [];
        $all = $request->allFiles();
        array_walk_recursive($all, function ($f, $name) use (&$files) {
            if ($f instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $files[] = [$name, $f->getClientOriginalName(), $f->getSize(), is_file((string) $f->getRealPath()) ? sha1_file($f->getRealPath()) : null];
            }
        });

        return hash('sha256', json_encode([$request->query->all(), $request->post(), $request->json()->all(), $files], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    private function error(string $message, string $code, int $status): JsonResponse
    {
        return new JsonResponse(['success' => false, 'message' => $message, 'error' => $code], $status);
    }
}
