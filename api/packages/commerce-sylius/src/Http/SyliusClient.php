<?php

namespace Ulams\CommerceSylius\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The Sylius Admin API (`/api/v2/admin`), signed in as the LMS's one admin API user. The base URL and the credentials
 * come from the platform environment (ADR 0131); the bearer token is cached for most of its lifetime and renewed once
 * on a 401. Reads answer null on 404 so callers can tell "missing" from "failed". Nothing here logs a body or a token.
 */
class SyliusClient
{
    private const TOKEN_TTL = 3000;

    public function __construct(private readonly ?string $baseUrl, private readonly ?string $email, private readonly ?string $password, private readonly int $timeout = 15)
    {
    }

    public static function fromConfig(): self
    {
        return new self(config('commerce-sylius.url'), config('commerce-sylius.admin.email'), config('commerce-sylius.admin.password'), (int) config('commerce-sylius.timeout', 15));
    }

    public function configured(): bool
    {
        return is_string($this->baseUrl) && $this->baseUrl !== '' && is_string($this->email) && $this->email !== '' && is_string($this->password) && $this->password !== '';
    }

    /** @return array<string,mixed>|null null when Sylius answers 404 */
    public function get(string $path, array $query = []): ?array
    {
        return $this->send('GET', $path, $query, null, true);
    }

    /** @return array<string,mixed> */
    public function post(string $path, array $body): array
    {
        return $this->send('POST', $path, [], $body, false) ?? [];
    }

    /** @return array<string,mixed> */
    public function put(string $path, array $body): array
    {
        return $this->send('PUT', $path, [], $body, false) ?? [];
    }

    /** @return array<string,mixed> */
    public function patch(string $path, array $body = []): array
    {
        return $this->send('PATCH', $path, [], $body, false) ?? [];
    }

    /** @return array<string,mixed>|null */
    private function send(string $method, string $path, array $query, ?array $body, bool $nullOn404): ?array
    {
        if (!$this->configured()) {
            throw new SyliusException('The Sylius service is not configured (COMMERCE_SYLIUS_URL and the admin API user).');
        }
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $response = $this->request($method, $path, $query, $body, $attempt === 1);
            if ($response->status() === 401 && $attempt === 0) {
                continue; // the cached token expired or was revoked: sign in again once
            }
            break;
        }
        if ($response->status() === 404 && $nullOn404) {
            return null;
        }
        if ($response->failed()) {
            throw new SyliusException($this->describe($method, $path, $response), $response->status());
        }
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function request(string $method, string $path, array $query, ?array $body, bool $freshToken): Response
    {
        try {
            $pending = $this->pending($this->token($freshToken))
                ->withHeaders(['Content-Type' => $method === 'PATCH' ? 'application/merge-patch+json' : 'application/ld+json']);
            $url = rtrim((string) $this->baseUrl, '/') . $path;

            return match ($method) {
                'GET' => $pending->get($url, $query),
                default => $pending->send($method, $url, ['body' => json_encode($body ?? new \stdClass(), JSON_THROW_ON_ERROR)]),
            };
        } catch (ConnectionException $e) {
            throw new SyliusException('The Sylius service is unreachable.');
        }
    }

    private function pending(string $token): PendingRequest
    {
        return Http::withToken($token)->accept('application/ld+json')->timeout($this->timeout)->connectTimeout(5)->withoutRedirecting();
    }

    private function token(bool $fresh): string
    {
        $key = 'commerce-sylius:token:' . hash('sha256', $this->baseUrl . '|' . $this->email . '|' . $this->password);
        if (!$fresh && is_string($cached = Cache::get($key)) && $cached !== '') {
            return $cached;
        }
        try {
            $response = Http::acceptJson()->asJson()->timeout($this->timeout)->connectTimeout(5)->withoutRedirecting()
                ->post(rtrim((string) $this->baseUrl, '/') . '/api/v2/admin/administrators/token', ['email' => $this->email, 'password' => $this->password]);
        } catch (ConnectionException) {
            throw new SyliusException('The Sylius service is unreachable.');
        }
        $token = $response->json('token');
        if ($response->failed() || !is_string($token) || $token === '') {
            throw new SyliusException('Sylius refused the admin API user (check COMMERCE_SYLIUS_ADMIN_EMAIL and COMMERCE_SYLIUS_ADMIN_PASSWORD).', $response->status());
        }
        Cache::put($key, $token, self::TOKEN_TTL);

        return $token;
    }

    private function describe(string $method, string $path, Response $response): string
    {
        $detail = $response->json('detail') ?? $response->json('hydra:description') ?? $response->json('message');
        // the path of an order holds its token (a bearer secret for the shop API): keep only the resource
        $safe = preg_replace('#(/orders/)[^/?]+#', '$1…', $path);

        return "Sylius answered HTTP {$response->status()} to {$method} {$safe}" . (is_string($detail) ? ': ' . mb_substr($detail, 0, 300) : '');
    }
}
