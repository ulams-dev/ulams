<?php

namespace Ulams\CommerceSylius;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Ulams\Commerce\Contracts\CommerceProvider;
use Ulams\Commerce\Contracts\InspectsCatalogue;
use Ulams\Commerce\Contracts\ListsOrders;
use Ulams\Commerce\Contracts\ProvisionsChannels;
use Ulams\Commerce\Contracts\ReconcilesOrders;
use Ulams\Commerce\Contracts\ReportsOrderEvents;
use Ulams\Commerce\Services\ProductLinkStore;
use Ulams\Commerce\Support\ChannelRef;
use Ulams\Commerce\Support\ChannelSpec;
use Ulams\Commerce\Support\CheckoutRef;
use Ulams\Commerce\Support\OrderEvent;
use Ulams\Commerce\Support\OrderEventEnvelope;
use Ulams\Commerce\Support\OrderEventPage;
use Ulams\Commerce\Support\OrderItemSnapshot;
use Ulams\Commerce\Support\OrderSnapshot;
use Ulams\Commerce\Support\ProductRef;
use Ulams\Commerce\Support\ProductSpec;
use Ulams\Commerce\Support\ProductState;
use Ulams\Commerce\Support\SellableRef;
use Ulams\Core\Models\User;
use Ulams\CommerceSylius\Http\SyliusClient;
use Ulams\CommerceSylius\Http\SyliusException;

/**
 * The Sylius 2.x adapter (ADR 0131). One Sylius instance serves every tenant with one channel per tenant; this adapter
 * is bound to the tenant's channel (`commerce.channel`) and never reads or writes another one.
 *
 * - Catalogue: one product per sellable, code `ul-<channel>-<type>-<id>`, one variant `<code>-v` that needs no shipping,
 *   with the channel price. Disabling is `enabled=false` on both, never a delete: orders reference the variant.
 * - Orders: `fetchOrder()` reads `GET /admin/orders/{token}` and the customer's `ulamsUserRef` (`<channel>:<user id>`,
 *   written by the LMS; a reference to another channel counts as unlinked). The LMS acts on that, not on a webhook.
 * - Events: the bridge bundle's outbox feed (`orderEventsAfter`) and signed webhooks (`ReportsOrderEvents`).
 * - Sweep: `ordersChangedSince()` lists the channel's completed orders.
 * - Channels: `ensureChannel()` makes the tenant's channel (currency, locale, countries, tax zone, payment methods and the
 *   bridge's webhook link) and is safe to repeat; the base currency cannot change and is never touched.
 */
final class SyliusProvider implements CommerceProvider, ReportsOrderEvents, ReconcilesOrders, InspectsCatalogue, ListsOrders, ProvisionsChannels
{
    public const KEY = 'sylius';
    private const ADMIN = '/api/v2/admin';

    /** @var array<string,array{currency:string,locale:string}> */
    private array $channels = [];

    public function __construct(private readonly SyliusClient $client, private readonly ProductLinkStore $links = new ProductLinkStore())
    {
    }

    public function key(): string
    {
        return self::KEY;
    }

    /** The product code of a sellable in this tenant's channel. */
    public function productCode(SellableRef $ref): string
    {
        return strtolower('ul-' . $this->channel() . '-' . str_replace('_', '-', $ref->type) . '-' . $ref->id);
    }

    public function syncProduct(SellableRef $ref, ProductSpec $spec): ProductRef
    {
        $channel = $this->channel();
        ['currency' => $currency, 'locale' => $locale] = $this->channelInfo($channel);
        if ($spec->price->currency !== $currency) {
            throw new InvalidArgumentException("The Sylius channel \"{$channel}\" sells in {$currency}; a price in {$spec->price->currency} cannot be used.");
        }
        $code = $this->productCode($ref);
        $variantCode = $code . '-v';
        $channelIri = self::ADMIN . '/channels/' . $channel;
        $name = mb_substr($spec->name, 0, 250);
        $description = $spec->description === null ? null : mb_substr($spec->description, 0, 10000);
        $taxCategory = $this->ensureTaxCategory();

        $existing = $this->client->get(self::ADMIN . '/products/' . $code);
        if ($existing === null) {
            $this->client->post(self::ADMIN . '/products', [
                'code' => $code, 'enabled' => $spec->active, 'channels' => [$channelIri],
                'translations' => [$locale => ['name' => $name, 'slug' => $code, 'description' => $description]],
            ]);
        } else {
            $this->client->put(self::ADMIN . '/products/' . $code, [
                'enabled' => $spec->active,
                'channels' => array_values(array_unique([...array_map('strval', $existing['channels'] ?? []), $channelIri])),
                // updating a translation must name it (`@id`), or Sylius reads it as a second translation for the locale
                'translations' => [$locale => $this->translation($existing, $locale, ['name' => $name, 'slug' => $existing['translations'][$locale]['slug'] ?? $code, 'description' => $description])],
            ]);
        }

        $variant = [
            'enabled' => $spec->active, 'shippingRequired' => false, 'tracked' => false,
            'taxCategory' => self::ADMIN . '/tax-categories/' . $taxCategory,
            'channelPricings' => [$channel => ['channelCode' => $channel, 'price' => $spec->price->amountMinor]],
            'translations' => [$locale => ['name' => $name]],
        ];
        $existingVariant = $this->client->get(self::ADMIN . '/product-variants/' . $variantCode);
        if ($existingVariant === null) {
            $this->client->post(self::ADMIN . '/product-variants', ['code' => $variantCode, 'product' => self::ADMIN . '/products/' . $code] + $variant);
        } else {
            $variant['translations'] = [$locale => $this->translation($existingVariant, $locale, ['name' => $name])];
            $pricing = $existingVariant['channelPricings'][$channel]['@id'] ?? null;
            if (is_string($pricing)) {
                $variant['channelPricings'][$channel] = ['@id' => $pricing] + $variant['channelPricings'][$channel];
            }
            $this->client->put(self::ADMIN . '/product-variants/' . $variantCode, $variant);
        }

        $product = new ProductRef(self::KEY, $code);
        $this->links->record($ref, $product, $spec, $variantCode, $channel);

        return $product;
    }

    public function ensureChannel(ChannelSpec $spec): ChannelRef
    {
        $a = self::ADMIN;
        foreach ([['currencies', $spec->currency], ['locales', $spec->locale]] as [$collection, $code]) {
            if ($this->client->get("{$a}/{$collection}/{$code}") === null) {
                $this->client->post("{$a}/{$collection}", ['code' => $code]);
            }
        }
        $seeder = new EuVatSeeder($this->client);
        $countries = array_values(array_unique([...$seeder->countries(), ...$spec->countries, $spec->sellerCountry]));
        foreach ($countries as $country) {
            if ($this->client->get("{$a}/countries/{$country}") === null) {
                $this->client->post("{$a}/countries", ['code' => $country, 'enabled' => true]);
            }
        }
        // the seller's tax zone must exist before the channel can name it; the full seed corrects rates (ulams:commerce:seed-eu-vat)
        $zone = 'eu-' . strtolower($spec->sellerCountry);
        if ($this->client->get("{$a}/zones/{$zone}") === null) {
            $seeder->seed();
        }

        $body = [
            'name' => mb_substr($spec->name, 0, 250), 'enabled' => true,
            'currencies' => ["{$a}/currencies/{$spec->currency}"], 'locales' => ["{$a}/locales/{$spec->locale}"],
            'countries' => array_map(fn (string $c) => "{$a}/countries/{$c}", $countries),
            'defaultTaxZone' => "{$a}/zones/{$zone}", 'taxCalculationStrategy' => 'order_items_based',
            'skippingShippingStepAllowed' => true, 'shippingAddressInCheckoutRequired' => false,
        ] + ($spec->hostname !== null ? ['hostname' => $spec->hostname] : []);

        $existing = $this->client->get("{$a}/channels/{$spec->code}");
        $created = $existing === null;
        if ($created) {
            $this->client->post("{$a}/channels", ['code' => $spec->code, 'baseCurrency' => "{$a}/currencies/{$spec->currency}", 'defaultLocale' => "{$a}/locales/{$spec->locale}"] + $body);
        } else {
            $current = $this->codeFromIri((string) ($existing['baseCurrency'] ?? ''));
            if ($current !== $spec->currency) {
                throw new SyliusException("The Sylius channel \"{$spec->code}\" sells in {$current}; the base currency cannot change, so it cannot be moved to {$spec->currency}.", 409);
            }
            // keep what an operator added by hand: more currencies, locales and countries stay
            foreach (['currencies', 'locales', 'countries'] as $key) {
                $body[$key] = array_values(array_unique([...array_map('strval', (array) ($existing[$key] ?? [])), ...$body[$key]]));
            }
            $this->client->put("{$a}/channels/{$spec->code}", $body);
        }
        unset($this->channels[$spec->code]);

        $this->ensurePaymentMethods("{$a}/channels/{$spec->code}", $spec->locale);
        if ($spec->webhookUrl !== null) {
            $this->client->put("{$a}/ulams/channel-links/{$spec->code}", ['webhookUrl' => $spec->webhookUrl, 'secret' => $spec->webhookSecret]);
        }

        return new ChannelRef(self::KEY, $spec->code, $created);
    }

    public function disableChannel(string $code): void
    {
        if ($this->client->get(self::ADMIN . "/channels/{$code}") !== null) {
            $this->client->put(self::ADMIN . "/channels/{$code}", ['enabled' => false]);
        }
        unset($this->channels[$code]);
    }

    /**
     * Enables the configured payment methods on the channel (they are shared by all channels; a method that exists only
     * gains this channel). Without any configured method the channel can be created but nothing can be paid.
     */
    private function ensurePaymentMethods(string $channelIri, string $locale): void
    {
        $methods = [];
        if (config('commerce-sylius.payments.offline')) {
            $methods[] = ['offline', 'Pay later (development)', 'offline', new \stdClass()];
        }
        $stripe = (array) config('commerce-sylius.payments.stripe');
        if (is_string($stripe['publishable_key'] ?? null) && $stripe['publishable_key'] !== '' && is_string($stripe['secret_key'] ?? null) && $stripe['secret_key'] !== '') {
            $methods[] = ['stripe', 'Card, BLIK and more (Stripe)', 'stripe_checkout', ['publishable_key' => $stripe['publishable_key'], 'secret_key' => $stripe['secret_key']]];
        }
        foreach ($methods as $position => [$code, $name, $factory, $config]) {
            $path = self::ADMIN . '/payment-methods/' . $code;
            $existing = $this->client->get($path);
            if ($existing === null) {
                $this->client->post(self::ADMIN . '/payment-methods', [
                    'code' => $code, 'enabled' => true, 'position' => $position, 'channels' => [$channelIri],
                    'gatewayConfig' => ['factoryName' => $factory, 'gatewayName' => $code, 'config' => $config],
                    'translations' => [$locale => ['name' => $name]],
                ]);
                continue;
            }
            $update = ['enabled' => true, 'channels' => array_values(array_unique([...array_map('strval', (array) ($existing['channels'] ?? [])), $channelIri]))];
            if (is_array($config) && ($existing['gatewayConfig']['config'] ?? null) != $config) {
                $update['gatewayConfig'] = ['@id' => $existing['gatewayConfig']['@id'] ?? null, 'factoryName' => $factory, 'gatewayName' => $code, 'config' => $config];
                $update['gatewayConfig'] = array_filter($update['gatewayConfig'], fn ($v) => $v !== null);
            }
            $this->client->put($path, $update);
        }
    }

    public function createCheckout(User $user, ProductRef $product, string $returnUrl): CheckoutRef
    {
        $base = rtrim((string) config('commerce.front_url', ''), '/');

        return new CheckoutRef($base . config('commerce-sylius.checkout_path', '/checkout/start') . '?' . http_build_query(['product' => $product->externalId, 'return' => $returnUrl]), $product->externalId);
    }

    public function handleOrderEvent(Request $request): ?OrderEvent
    {
        return null; // events arrive through OrderWebhookController and the feed, as envelopes
    }

    public function fetchOrder(string $orderId): OrderSnapshot
    {
        $channel = $this->channel();
        $order = $this->client->get(self::ADMIN . '/orders/' . rawurlencode($orderId));
        if ($order === null) {
            throw new SyliusException('Sylius has no such order.', 404);
        }

        return $this->snapshot($order, $channel);
    }

    public function orderEventsAfter(?string $cursor, int $limit): OrderEventPage
    {
        $channel = $this->channel();
        $feed = $this->client->get(self::ADMIN . '/ulams/events', ['channel' => $channel, 'after' => (int) $cursor, 'limit' => $limit]);
        if ($feed === null) {
            throw new SyliusException('The Sylius bridge bundle does not serve the event feed.', 404);
        }
        $events = [];
        foreach ((array) ($feed['events'] ?? []) as $payload) {
            try {
                $events[] = OrderEventEnvelope::fromPayload((array) $payload);
            } catch (InvalidArgumentException $e) {
                // the bridge writes valid envelopes; one that is not must not stop the cursor
                Log::warning('commerce-sylius: skipped a malformed feed event', ['reason' => $e->getMessage()]);
            }
        }

        return new OrderEventPage($events, isset($feed['nextCursor']) ? (string) $feed['nextCursor'] : $cursor);
    }

    public function inspectProduct(ProductRef $product): ?ProductState
    {
        $channel = $this->channel();
        ['currency' => $currency, 'locale' => $locale] = $this->channelInfo($channel);
        $data = $this->client->get(self::ADMIN . '/products/' . $product->externalId);
        $variant = $this->client->get(self::ADMIN . '/product-variants/' . $product->externalId . '-v');
        if ($data === null || $variant === null) {
            return null;
        }
        $price = $variant['channelPricings'][$channel]['price'] ?? null;
        if ($price === null) {
            return null; // the variant is not priced in this channel: as good as missing for this tenant
        }

        return new ProductState(
            $product,
            (string) ($data['translations'][$locale]['name'] ?? ''),
            (int) $price,
            $currency,
            (bool) ($data['enabled'] ?? false) && (bool) ($variant['enabled'] ?? false),
        );
    }

    public function ordersChangedSince(CarbonInterface $since): iterable
    {
        $channel = $this->channel();
        for ($page = 1; $page <= 200; $page++) {
            $result = $this->client->get(self::ADMIN . '/orders', [
                'channel.code' => $channel, 'checkoutCompletedAt[after]' => $since->toIso8601String(),
                'page' => $page, 'itemsPerPage' => 30, 'order[number]' => 'asc',
            ]);
            $members = (array) ($result['hydra:member'] ?? $result['member'] ?? []);
            foreach ($members as $summary) {
                $payment = (string) ($summary['paymentState'] ?? '');
                $cancelled = ($summary['state'] ?? '') === OrderSnapshot::STATE_CANCELLED;
                if (!$cancelled && !in_array($payment, [OrderSnapshot::PAYMENT_PAID, OrderSnapshot::PAYMENT_PARTIALLY_REFUNDED, OrderSnapshot::PAYMENT_REFUNDED], true)) {
                    continue;
                }
                // the list has no lines: read the order itself
                yield $this->fetchOrder((string) $summary['tokenValue']);
            }
            if (count($members) < 30) {
                return;
            }
        }
    }

    /** @param array<string,mixed> $order */
    private function snapshot(array $order, string $expectedChannel): OrderSnapshot
    {
        $channel = $this->codeFromIri((string) ($order['channel'] ?? ''));
        $items = [];
        foreach ((array) ($order['items'] ?? []) as $item) {
            $variant = $this->codeFromIri((string) ($item['variant'] ?? ''));
            $items[] = new OrderItemSnapshot((string) $item['id'], $variant, (int) ($item['quantity'] ?? 1), false);
        }

        return new OrderSnapshot(
            (string) $order['tokenValue'],
            $channel,
            $channel === $expectedChannel ? $this->userRef((string) ($order['customer'] ?? ''), $channel) : null,
            (string) ($order['state'] ?? ''),
            (string) ($order['paymentState'] ?? ''),
            (string) ($order['currencyCode'] ?? ''),
            (int) ($order['total'] ?? 0),
            $items,
            isset($order['number']) ? (string) $order['number'] : null,
            (string) $order['tokenValue'],
            (int) ($order['taxTotal'] ?? 0),
        );
    }

    /** The LMS user id the Sylius customer is linked to (`<channel>:<id>`); null when unlinked or linked in another channel. */
    private function userRef(string $customerIri, string $channel): ?int
    {
        if ($customerIri === '') {
            return null;
        }
        $customer = $this->client->get($customerIri);
        $ref = is_array($customer) ? ($customer['ulamsUserRef'] ?? null) : null;
        if (!is_string($ref) || !preg_match('/^(.+):(\d+)$/', $ref, $m) || $m[1] !== $channel) {
            return null;
        }

        return (int) $m[2];
    }

    /** @return array{currency:string,locale:string} */
    private function channelInfo(string $channel): array
    {
        if (isset($this->channels[$channel])) {
            return $this->channels[$channel];
        }
        $data = $this->client->get(self::ADMIN . '/channels/' . $channel);
        if ($data === null) {
            throw new SyliusException("The Sylius channel \"{$channel}\" does not exist; provision the tenant's channel first.", 404);
        }

        return $this->channels[$channel] = [
            'currency' => $this->codeFromIri((string) ($data['baseCurrency'] ?? '')),
            'locale' => $this->codeFromIri((string) ($data['defaultLocale'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $resource
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    private function translation(array $resource, string $locale, array $fields): array
    {
        $id = $resource['translations'][$locale]['@id'] ?? null;

        return is_string($id) ? ['@id' => $id] + $fields : $fields;
    }

    private function ensureTaxCategory(): string
    {
        $code = (string) config('commerce-sylius.tax_category', 'e-service');
        if ($this->client->get(self::ADMIN . '/tax-categories/' . $code) === null) {
            $this->client->post(self::ADMIN . '/tax-categories', ['code' => $code, 'name' => 'Electronic service']);
        }

        return $code;
    }

    private function channel(): string
    {
        $channel = (string) config('commerce.channel');
        if ($channel === '' || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $channel)) {
            throw new RuntimeException('commerce.channel (COMMERCE_CHANNEL) is not set: this tenant has no Sylius channel.');
        }

        return $channel;
    }

    private function codeFromIri(string $iri): string
    {
        return rawurldecode(basename(parse_url($iri, PHP_URL_PATH) ?: ''));
    }
}
