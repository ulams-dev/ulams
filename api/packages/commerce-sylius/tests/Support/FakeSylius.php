<?php

namespace Ulams\CommerceSylius\Tests\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A small in-memory Sylius Admin API behind `Http::fake`, for tests that must not need the real service. It keeps
 * products, variants and orders in arrays, answers 404 like Sylius for what is missing, and records every request.
 * The shapes follow the responses recorded from Sylius 2.3 in tests/fixtures.
 */
final class FakeSylius
{
    /** @var array<string,array<string,mixed>> */
    public array $products = [];
    /** @var array<string,array<string,mixed>> */
    public array $variants = [];
    /** @var array<string,array<string,mixed>> */
    public array $orders = [];
    /** @var array<string,array<string,mixed>> */
    public array $customers = [];
    /** @var list<array<string,mixed>> */
    public array $feed = [];
    public bool $channelExists = true;
    public string $currency = 'EUR';
    public bool $down = false;
    public int $tokenRequests = 0;
    public int $unauthorizedOnce = 0;
    /** @var list<array{string,string,mixed}> method, path, body */
    public array $requests = [];

    public function install(): self
    {
        Http::fake(fn (Request $request) => $this->handle($request));

        return $this;
    }

    private function handle(Request $request)
    {
        $path = parse_url($request->url(), PHP_URL_PATH);
        $method = $request->method();
        $body = json_decode($request->body(), true);
        if ($this->down) {
            throw new \Illuminate\Http\Client\ConnectionException('connection refused');
        }
        if ($path === '/api/v2/admin/administrators/token') {
            ++$this->tokenRequests;

            return Http::response(['token' => 'jwt-' . $this->tokenRequests]);
        }
        if ($this->unauthorizedOnce > 0) {
            --$this->unauthorizedOnce;

            return Http::response(['message' => 'Expired JWT Token'], 401);
        }
        $this->requests[] = [$method, $path, $body];
        $admin = '/api/v2/admin';
        $rel = substr($path, strlen($admin));

        if ($rel === '/channels/acme') {
            return $this->channelExists ? Http::response(['baseCurrency' => "{$admin}/currencies/{$this->currency}", 'defaultLocale' => "{$admin}/locales/en_US"]) : Http::response([], 404);
        }
        if ($rel === '/tax-categories/e-service') {
            return Http::response(['code' => 'e-service']);
        }
        if (preg_match('#^/products/([^/]+)$#', $rel, $m)) {
            return $this->resource($this->products, $m[1], $method, $body, 'product');
        }
        if ($rel === '/products' && $method === 'POST') {
            return $this->resource($this->products, $body['code'], 'POST', $body, 'product');
        }
        if (preg_match('#^/product-variants/([^/]+)$#', $rel, $m)) {
            return $this->resource($this->variants, $m[1], $method, $body, 'variant');
        }
        if ($rel === '/product-variants' && $method === 'POST') {
            return $this->resource($this->variants, $body['code'], 'POST', $body, 'variant');
        }
        if (preg_match('#^/orders/([^/]+)$#', $rel, $m)) {
            return isset($this->orders[rawurldecode($m[1])]) ? Http::response($this->orders[rawurldecode($m[1])]) : Http::response([], 404);
        }
        if ($rel === '/orders') {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 1);
            $all = array_values($this->orders);

            return Http::response(['hydra:member' => array_slice($all, ($page - 1) * 30, 30)]);
        }
        if (preg_match('#^/customers/(\d+)$#', $rel, $m)) {
            return isset($this->customers[$m[1]]) ? Http::response($this->customers[$m[1]]) : Http::response([], 404);
        }
        if ($rel === '/ulams/events') {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $after = (int) ($query['after'] ?? 0);
            $events = array_slice($this->feed, $after, (int) ($query['limit'] ?? 200));

            return Http::response(['events' => $events, 'nextCursor' => (string) ($after + count($events))]);
        }

        return Http::response(['detail' => "unexpected {$method} {$path}"], 500);
    }

    /** @param array<string,array<string,mixed>> $store */
    private function resource(array &$store, string $code, string $method, mixed $body, string $kind)
    {
        if ($method === 'GET') {
            return isset($store[$code]) ? Http::response($store[$code]) : Http::response([], 404);
        }
        if ($method === 'POST') {
            $store[$code] = $this->shape($kind, $body);

            return Http::response($store[$code], 201);
        }
        if (!isset($store[$code])) {
            return Http::response([], 404);
        }
        // Sylius reads a translation or a channel price without its `@id` as a second one for the same locale/channel
        foreach (['translations', 'channelPricings'] as $collection) {
            foreach ($body[$collection] ?? [] as $key => $entry) {
                if (isset($store[$code][$collection][$key]) && !isset($entry['@id'])) {
                    return Http::response(['detail' => "{$collection}[{$key}]: already exists (the update must carry its @id)"], 422);
                }
            }
        }
        $store[$code] = $this->shape($kind, $body + $store[$code]);

        return Http::response($store[$code]);
    }

    /** What the real API holds after a write: the fields the adapter reads back, with the `@id`s Sylius gives sub-resources. */
    private function shape(string $kind, array $body): array
    {
        foreach (['translations', 'channelPricings'] as $collection) {
            foreach ($body[$collection] ?? [] as $key => $entry) {
                $body[$collection][$key]['@id'] ??= "/api/v2/admin/{$kind}/{$collection}/{$key}";
            }
        }

        return $body;
    }

    /** A paid order of the channel with one line per variant code, linked to an LMS user. */
    public function order(string $token, array $variants, ?int $userRef, string $paymentState = 'paid', string $state = 'new', string $channel = 'acme', ?string $ref = null): void
    {
        $customerId = (string) (count($this->customers) + 1);
        $this->customers[$customerId] = ['ulamsUserRef' => $ref ?? ($userRef === null ? null : "{$channel}:{$userRef}")];
        $items = [];
        $n = 0;
        foreach ($variants as $variant) {
            $items[] = ['id' => 900 + ++$n, 'variant' => "/api/v2/admin/product-variants/{$variant}", 'quantity' => 1];
        }
        $this->orders[$token] = [
            'tokenValue' => $token, 'number' => '0000' . count($this->orders), 'channel' => "/api/v2/admin/channels/{$channel}",
            'customer' => "/api/v2/admin/customers/{$customerId}", 'state' => $state, 'paymentState' => $paymentState,
            'currencyCode' => 'EUR', 'total' => 4900, 'taxTotal' => 0, 'items' => $items,
        ];
    }

    /** @return list<array{string,string,mixed}> */
    public function writes(): array
    {
        return array_values(array_filter($this->requests, fn (array $r) => $r[0] !== 'GET'));
    }
}
