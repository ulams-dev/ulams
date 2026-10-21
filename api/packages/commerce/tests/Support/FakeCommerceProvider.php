<?php

namespace Ulams\Commerce\Tests\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Ulams\Commerce\Contracts\CommerceProvider;
use Ulams\Commerce\Contracts\FetchesOrders;
use Ulams\Commerce\Contracts\ReconcilesOrders;
use Ulams\Commerce\Support\CheckoutRef;
use Ulams\Commerce\Support\OrderEvent;
use Ulams\Commerce\Support\OrderEventEnvelope;
use Ulams\Commerce\Support\OrderEventPage;
use Ulams\Commerce\Support\OrderItemSnapshot;
use Ulams\Commerce\Support\OrderSnapshot;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\ProductRef;
use Ulams\Commerce\Support\SellableRef;
use Ulams\Core\Models\User;

/**
 * STUB of Sylius for CM-1 (ADR 0130): no Sylius instance exists yet, so this in-memory provider plays the part of
 * its Admin API (`fetchOrder`), its bridge event feed (`orderEventsAfter`) and its outbox (each state change appends
 * an event with a per-order sequence, which a test may deliver, delay, duplicate or drop). Replaced by the real
 * adapter and the `commerce` compose profile in CM-3; the contract it exercises is the one the bridge bundle will
 * have to meet.
 */
class FakeCommerceProvider implements CommerceProvider, FetchesOrders, ReconcilesOrders
{
    /** @var array<string,array<string,mixed>> */
    public array $orders = [];

    /** @var list<array<string,mixed>> events in the order Sylius recorded them (the "outbox") */
    public array $feed = [];

    /** Simulates the Admin API being unreachable. */
    public bool $down = false;

    public int $fetches = 0;

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

    public function syncProduct(SellableRef $ref, Price $price, bool $active): ProductRef
    {
        return new ProductRef('fake', "variant-{$ref->type}-{$ref->id}");
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
