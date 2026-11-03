<?php

namespace Ulams\Commerce\Tests\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Ulams\Commerce\Contracts\CommerceProvider;
use Ulams\Commerce\Contracts\InspectsCatalogue;
use Ulams\Commerce\Contracts\ListsOrders;
use Ulams\Commerce\Contracts\ReportsOrderEvents;
use Ulams\Commerce\Services\ProductLinkStore;
use Ulams\Commerce\Support\ProductState;
use Ulams\Commerce\Contracts\ReconcilesOrders;
use Ulams\Commerce\Support\CheckoutRef;
use Ulams\Commerce\Support\OrderEvent;
use Ulams\Commerce\Support\OrderEventEnvelope;
use Ulams\Commerce\Support\OrderEventPage;
use Ulams\Commerce\Support\OrderItemSnapshot;
use Ulams\Commerce\Support\OrderSnapshot;
use Ulams\Commerce\Support\ProductSpec;
use Ulams\Commerce\Support\ProductRef;
use Ulams\Commerce\Support\SellableRef;
use Ulams\Core\Models\User;

/**
 * An in-memory stand-in for a webhook provider (ADR 0130): it plays the part of Sylius's Admin API (`fetchOrder`,
 * `inspectProduct`, order listing), of the bridge's event feed (`orderEventsAfter`) and of its outbox (each state change
 * appends an event with a per-order sequence, which a test may deliver, delay, duplicate or drop). The real adapter is
 * tested against a real Sylius in `commerce-sylius`; this one lets the LMS side (webhook, applier, sweep, catalogue
 * sync) run in every CI job without a Sylius service.
 */
class FakeCommerceProvider implements CommerceProvider, ReportsOrderEvents, ReconcilesOrders, InspectsCatalogue, ListsOrders
{
    /** @var array<string,array<string,mixed>> */
    public array $orders = [];

    /** @var list<array<string,mixed>> events in the order Sylius recorded them (the "outbox") */
    public array $feed = [];

    /** Simulates the Admin API being unreachable. */
    public bool $down = false;

    public int $fetches = 0;

    /** @var array<string,ProductState> products by external id, as the provider holds them */
    public array $products = [];

    /** @var list<array{string,bool}> every syncProduct call: sellable key, active */
    public array $syncCalls = [];

    /** Fails syncProduct with this message while set (the provider is down). */
    public ?string $syncFails = null;

    public function __construct(public string $channel = 'acme')
    {
    }

    /** @param array<string,string> $items order item id => product reference (variant code) */
    public function placeOrder(string $orderId, ?int $userRef, array $items, ?string $channel = null): void
    {
        $this->orders[$orderId] = [
            'channel' => $channel ?? $this->channel, 'userRef' => $userRef, 'state' => 'new', 'paymentState' => 'awaiting_payment',
            'items' => array_map(fn (string $ref) => ['productRef' => $ref, 'refunded' => false], $items), 'sequence' => 0,
        ];
    }

    /** @return array<string,mixed> the event payload the bridge would POST */
    public function pay(string $orderId): array
    {
        $this->orders[$orderId]['state'] = 'new';
        $this->orders[$orderId]['paymentState'] = 'paid';

        return $this->emit($orderId, 'order.paid');
    }

    /** Refunds the whole order, or only the units of the given items. */
    public function refund(string $orderId, ?array $itemIds = null): array
    {
        if ($itemIds === null) {
            $this->orders[$orderId]['paymentState'] = 'refunded';
        } else {
            foreach ($itemIds as $id) {
                $this->orders[$orderId]['items'][$id]['refunded'] = true;
            }
            $this->orders[$orderId]['paymentState'] = 'partially_refunded';
        }

        return $this->emit($orderId, $itemIds === null ? 'order.refunded' : 'order.partially_refunded');
    }

    /** A goodwill amount refund not tied to units: no item is flagged. */
    public function refundAmount(string $orderId): array
    {
        $this->orders[$orderId]['paymentState'] = 'partially_refunded';

        return $this->emit($orderId, 'order.partially_refunded');
    }

    public function cancel(string $orderId): array
    {
        $this->orders[$orderId]['state'] = 'cancelled';

        return $this->emit($orderId, 'order.cancelled');
    }

    /** @return array<string,mixed> */
    private function emit(string $orderId, string $type): array
    {
        $payload = [
            'version' => OrderEventEnvelope::VERSION, 'id' => (string) Str::ulid(), 'type' => $type,
            'channel_code' => $this->orders[$orderId]['channel'], 'order_id' => $orderId,
            'sequence' => ++$this->orders[$orderId]['sequence'],
        ];
        $this->feed[] = $payload;

        return $payload;
    }

    public function key(): string
    {
        return 'fake';
    }

    public function syncProduct(SellableRef $ref, ProductSpec $spec): ProductRef
    {
        if ($this->syncFails !== null) {
            throw new RuntimeException($this->syncFails);
        }
        $this->syncCalls[] = [$ref->key(), $spec->active];
        $product = new ProductRef('fake', "variant-{$ref->type}-{$ref->id}");
        $this->products[$product->externalId] = new ProductState($product, $spec->name, $spec->price->amountMinor, $spec->price->currency, $spec->active);
        (new ProductLinkStore())->record($ref, $product, $spec);

        return $product;
    }

    public function inspectProduct(ProductRef $product): ?ProductState
    {
        return $this->products[$product->externalId] ?? null;
    }

    public function ordersChangedSince(\Carbon\CarbonInterface $since): iterable
    {
        foreach (array_keys($this->orders) as $id) {
            yield $this->fetchOrder((string) $id);
        }
    }

    public function createCheckout(User $user, ProductRef $product, string $returnUrl): CheckoutRef
    {
        return new CheckoutRef("https://shop.test/checkout/{$product->externalId}");
    }

    public function handleOrderEvent(Request $request): ?OrderEvent
    {
        return null; // events arrive through OrderWebhookController
    }

    public function fetchOrder(string $orderId): OrderSnapshot
    {
        $this->fetches++;
        if ($this->down) {
            throw new RuntimeException('Sylius Admin API is unreachable.');
        }
        $o = $this->orders[$orderId] ?? throw new RuntimeException("Order {$orderId} not found.");
        $items = [];
        foreach ($o['items'] as $id => $item) {
            $items[] = new OrderItemSnapshot((string) $id, $item['productRef'], 1, $item['refunded']);
        }

        return new OrderSnapshot($orderId, $o['channel'], $o['userRef'], $o['state'], $o['paymentState'], 'EUR', 4900, $items, number: "000{$orderId}", token: "tok-{$orderId}");
    }

    public function orderEventsAfter(?string $cursor, int $limit): OrderEventPage
    {
        $from = (int) $cursor;
        $slice = array_slice($this->feed, $from, $limit);
        $events = array_map(fn (array $p) => OrderEventEnvelope::fromPayload($p), $slice);

        return new OrderEventPage($events, (string) ($from + count($slice)));
    }
}
