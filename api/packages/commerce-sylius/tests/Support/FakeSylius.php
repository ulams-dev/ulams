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
    /** @var array<string,array<string,array<string,mixed>>> generic collections (currencies, zones, tax-rates, payment-methods ...) by code */
    public array $resources = ['tax-categories' => ['e-service' => ['code' => 'e-service']]];
    /** @var array<string,array<string,mixed>> channels made through the API, by code */
    public array $channelsStore = [];
    /** @var array<string,array<string,mixed>> channel links (the bridge), by channel code */
    public array $links = [];
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

        if (preg_match('#^/channels/([^/]+)$#', $rel, $m)) {
            return $this->channel($m[1], $method, $body);
        }
        if ($rel === '/channels' && $method === 'POST') {
            return $this->channel($body['code'], 'POST', $body);
        }
        if (preg_match('#^/ulams/channel-links/([^/]+)$#', $rel, $m) && $method === 'PUT') {
            $this->links[$m[1]] = $body;

            return Http::response(['channelCode' => $m[1], 'webhookUrl' => $body['webhookUrl']], isset($this->links[$m[1]]) ? 200 : 201);
        }
        foreach (['currencies', 'locales', 'countries', 'zones', 'tax-rates', 'tax-categories', 'payment-methods'] as $collection) {
            if (preg_match('#^/' . $collection . '/([^/]+)$#', $rel, $m)) {
                return $this->generic($collection, $m[1], $method, $body);
            }
            if ($rel === "/{$collection}" && $method === 'POST') {
                return $this->generic($collection, (string) $body['code'], 'POST', $body);
            }
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

    private function channel(string $code, string $method, mixed $body)
    {
        $stored = $this->channelsStore[$code] ?? ($code === 'acme' && $this->channelExists ? ['code' => 'acme', 'baseCurrency' => "/api/v2/admin/currencies/{$this->currency}", 'defaultLocale' => '/api/v2/admin/locales/en_US'] : null);
        if ($method === 'GET') {
            return $stored === null ? Http::response([], 404) : Http::response($stored);
        }
        if ($method === 'POST') {
            return Http::response($this->channelsStore[$code] = $body, 201);
        }
        if ($stored === null) {
            return Http::response([], 404);
        }

        return Http::response($this->channelsStore[$code] = $body + $stored);
    }

    private function generic(string $collection, string $code, string $method, mixed $body)
    {
        $stored = $this->resources[$collection][$code] ?? null;
        if ($method === 'GET') {
            return $stored === null ? Http::response([], 404) : Http::response($stored);
        }
        if ($method === 'POST') {
            return Http::response($this->resources[$collection][$code] = $body, 201);
        }
        if ($stored === null) {
            return Http::response([], 404);
        }

        return Http::response($this->resources[$collection][$code] = $body + $stored);
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
