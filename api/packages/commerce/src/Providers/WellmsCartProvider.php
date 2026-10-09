<?php

namespace Ulams\Commerce\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Ulams\Cart\Enums\ProductType;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Services\Contracts\ProductServiceContract;
use Ulams\Commerce\Contracts\CommerceProvider;
use Ulams\Commerce\Models\ProductLink;
use Ulams\Commerce\Support\CheckoutRef;
use Ulams\Commerce\Support\OrderEvent;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\ProductRef;
use Ulams\Commerce\Support\SellableRef;
use Ulams\Core\Models\User;

/**
 * The built-in commerce backend: a cart `Product` per sellable, created through
 * `ProductServiceContract` (never by writing tables). The cart and payments packages grant access
 * themselves when a payment succeeds, so there are no order webhooks here.
 */
final class WellmsCartProvider implements CommerceProvider
{
    public const KEY = 'wellms';

    public function __construct(private readonly ProductServiceContract $products)
    {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function syncProduct(SellableRef $ref, Price $price, bool $active): ProductRef
    {
        $this->assertCurrency($price);
        $class = $this->productableClass($ref);
        $productable = $class::query()->findOrFail($ref->id);

        return DB::transaction(function () use ($ref, $price, $active, $class, $productable) {
            $link = ProductLink::query()->where(['sellable_type' => $ref->type, 'sellable_id' => $ref->id, 'provider' => self::KEY])->first();
            $product = $link !== null ? Product::query()->find((int) $link->external_id) : null;
            $product ??= $this->products->findSingleProductForProductable($productable);

            $data = [
                'name' => mb_substr((string) $productable->getName(), 0, 255),
                'description' => $productable->getDescription(),
                'type' => ProductType::SINGLE,
                'price' => $price->amountMinor,
                'purchasable' => $active,
                'tax_rate' => $product?->tax_rate ?? 0,
                'extra_fees' => $product?->extra_fees ?? 0,
                'productables' => [['class' => $class, 'id' => $productable->getKey()]],
            ];
            $product = $product !== null ? $this->products->update($product, $data) : $this->products->create($data);

            ProductLink::query()->updateOrCreate(
                ['sellable_type' => $ref->type, 'sellable_id' => $ref->id, 'provider' => self::KEY],
                ['external_id' => (string) $product->getKey(), 'amount_minor' => $price->amountMinor, 'currency' => $price->currency, 'active' => $active],
            );

            return new ProductRef(self::KEY, (string) $product->getKey());
        });
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
