<?php

namespace Ulams\Commerce\Tests\Feature;

use Ulams\Cart\Enums\OrderStatus;
use Ulams\Cart\Facades\Shop;
use Ulams\Cart\Models\Order;
use Ulams\Cart\Models\OrderItem;
use Ulams\Cart\Tests\Mocks\ExampleProductable;
use Ulams\Commerce\Contracts\CommerceProvider;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\ProductRef;
use Ulams\Commerce\Support\SellableRef;
use Ulams\Commerce\Tests\Support\ProviderContract;
use Ulams\Commerce\Tests\TestCase;
use Ulams\Core\Models\User;
use Ulams\Payments\Enums\PaymentStatus;
use Ulams\Payments\Models\Payment;

/** The provider contract, run against the Wellms cart adapter. The same trait runs against a real Sylius in `commerce-sylius`. */
class WellmsProviderContractTest extends TestCase
{
    use ProviderContract;

    protected function setUp(): void
    {
        parent::setUp();
        Shop::registerProductableClass(ExampleProductable::class);
        config(['ulams_payments.default_currency' => 'USD']);
    }

    protected function contractProvider(): CommerceProvider
    {
        return app(CommerceProvider::class);
    }

    protected function contractSellable(): SellableRef
    {
        return SellableRef::course(ExampleProductable::factory()->create(['name' => 'Brewing basics'])->getKey());
    }

    protected function contractPrice(int $amount): Price
    {
        return new Price($amount, 'USD');
    }

    protected function contractUser(): User
    {
        return User::factory()->create();
    }

    protected function contractPaidOrder(User $user, ProductRef $product): string
    {
        $order = Order::query()->create(['user_id' => $user->getKey(), 'status' => OrderStatus::PAID, 'total' => 4900, 'subtotal' => 4900, 'tax' => 0]);
        OrderItem::query()->create(['order_id' => $order->getKey(), 'buyable_type' => 'product', 'buyable_id' => (int) $product->externalId, 'quantity' => 1]);

        return (string) $order->getKey();
    }

    protected function contractRefund(string $orderId): void
    {
        Payment::query()->create([
            'amount' => 4900, 'currency' => 'USD', 'status' => PaymentStatus::REFUNDED, 'payable_type' => (new Order())->getMorphClass(),
            'payable_id' => (int) $orderId, 'driver' => 'stripe',
        ]);
    }
}
