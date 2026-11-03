<?php

namespace Ulams\Commerce\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Ulams\Cart\Enums\ProductType;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Services\Contracts\ProductServiceContract;
use Ulams\Cart\Enums\OrderStatus;
use Ulams\Cart\Models\Order;
use Ulams\Commerce\Contracts\CommerceProvider;
use Ulams\Commerce\Contracts\InspectsCatalogue;
use Ulams\Commerce\Services\ProductLinkStore;
use Ulams\Commerce\Support\OrderItemSnapshot;
use Ulams\Commerce\Support\OrderSnapshot;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\ProductSpec;
use Ulams\Commerce\Support\ProductState;
use Ulams\Commerce\Support\UnsupportedSellable;
use Ulams\Payments\Enums\PaymentStatus;
use Ulams\Commerce\Support\CheckoutRef;
use Ulams\Commerce\Support\OrderEvent;
use Ulams\Commerce\Support\ProductRef;
use Ulams\Commerce\Support\SellableRef;
use Ulams\Core\Models\User;

/**
 * The built-in commerce backend: a cart `Product` per sellable, created through
 * `ProductServiceContract` (never by writing tables). The cart and payments packages grant access
 * themselves when a payment succeeds, so there are no order webhooks here.
 */
final class WellmsCartProvider implements CommerceProvider, InspectsCatalogue
{
    public const KEY = 'wellms';

    public function __construct(private readonly ProductServiceContract $products, private readonly ProductLinkStore $links = new ProductLinkStore())
    {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function syncProduct(SellableRef $ref, ProductSpec $spec): ProductRef
    {
        if ($ref->type !== SellableRef::COURSE) {
            throw new UnsupportedSellable("The Wellms cart sells courses only, not a {$ref->type}.");
        }
        $this->assertCurrency($spec->price);
        $class = $this->productableClass($ref);
        $productable = $class::query()->findOrFail($ref->id);

        return DB::transaction(function () use ($ref, $spec, $class, $productable) {
            $link = $this->links->find($ref, self::KEY);
            $product = $link !== null ? Product::query()->find((int) $link->external_id) : null;
            $product ??= $this->products->findSingleProductForProductable($productable);

            $data = [
                'name' => mb_substr($spec->name, 0, 255),
                'description' => $productable->getDescription(),
                'type' => ProductType::SINGLE,
                'price' => $spec->price->amountMinor,
                'purchasable' => $spec->active,
                'tax_rate' => $product?->tax_rate ?? 0,
                'extra_fees' => $product?->extra_fees ?? 0,
                'productables' => [['class' => $class, 'id' => $productable->getKey()]],
            ];
            $product = $product !== null ? $this->products->update($product, $data) : $this->products->create($data);
            return $this->recordLink($ref, $product, $spec);
        });
    }

    private function recordLink(SellableRef $sellable, Product $product, ProductSpec $spec): ProductRef
    {
        $ref = new ProductRef(self::KEY, (string) $product->getKey());
        $this->links->record($sellable, $ref, $spec);

        return $ref;
    }

    public function inspectProduct(ProductRef $product): ?ProductState
    {
        $model = Product::query()->find((int) $product->externalId);

        return $model === null ? null : new ProductState($product, (string) $model->name, (int) $model->price, strtoupper((string) config('ulams_payments.default_currency', 'USD')), (bool) $model->purchasable);
    }

    /**
     * The cart order as an order snapshot, so one reader can compare any provider's orders with entitlements. Each line is
     * a product (the product id is its `productRef`); a refunded payment is a refunded order. The cart has no channel,
     * so it reports the tenant's configured one.
     */
    public function fetchOrder(string $orderId): OrderSnapshot
    {
        $order = Order::query()->with('items')->findOrFail((int) $orderId);
        $refunded = $order->payments()->where('status', PaymentStatus::REFUNDED)->exists();
        $paid = in_array($order->status, [OrderStatus::PAID, OrderStatus::TRIAL_PAID], true);
        $cancelled = in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::TRIAL_CANCELLED], true);
        $items = [];
        foreach ($order->items as $item) {
            $items[] = new OrderItemSnapshot((string) $item->getKey(), (string) $item->buyable_id, (int) $item->quantity, false);
        }

        return new OrderSnapshot(
            (string) $order->getKey(),
            (string) (config('commerce.channel') ?: self::KEY),
            $order->user_id === null ? null : (int) $order->user_id,
            $cancelled ? OrderSnapshot::STATE_CANCELLED : 'new',
            $refunded ? OrderSnapshot::PAYMENT_REFUNDED : ($paid ? OrderSnapshot::PAYMENT_PAID : 'awaiting_payment'),
            strtoupper((string) config('ulams_payments.default_currency', 'USD')),
            (int) $order->total,
            $items,
            number: (string) $order->getKey(),
            taxMinor: (int) $order->tax,
        );
    }

    public function createCheckout(User $user, ProductRef $product, string $returnUrl): CheckoutRef
    {
        $base = rtrim((string) config('commerce.front_url', ''), '/');

        return new CheckoutRef($base . '/cart?' . http_build_query(['product' => $product->externalId, 'return' => $returnUrl]), $product->externalId);
    }

    public function handleOrderEvent(Request $request): ?OrderEvent
    {
        return null;
    }

    /** The cart sells in one currency, set in the payments settings. */
    private function assertCurrency(Price $price): void
    {
        $currency = strtoupper((string) config('ulams_payments.default_currency', 'USD'));
        if ($price->currency !== $currency) {
            throw new InvalidArgumentException("The cart sells in {$currency}; a price in {$price->currency} cannot be used.");
        }
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model&\Ulams\Cart\Contracts\Productable> */
    private function productableClass(SellableRef $ref): string
    {
        $configured = config("commerce.sellables.{$ref->type}");
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }
        if ($ref->type === SellableRef::COURSE) {
            foreach ($this->products->listRegisteredProductableClasses() as $class) {
                if (is_a($class, \Ulams\Courses\Models\Course::class, true)) {
                    return $class;
                }
            }
        }

        throw new InvalidArgumentException("No productable class is registered for {$ref->type}.");
    }
}
