<?php

namespace Ulams\Commerce\Tests\Support;

use Ulams\Commerce\Contracts\CommerceProvider;
use Ulams\Commerce\Contracts\InspectsCatalogue;
use Ulams\Commerce\Models\ProductLink;
use Ulams\Commerce\Support\OrderSnapshot;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\ProductRef;
use Ulams\Commerce\Support\ProductSpec;
use Ulams\Commerce\Support\SellableRef;
use Ulams\Core\Models\User;

/**
 * What every `CommerceProvider` must do (interface v2, ADR 0130). One suite, run against the Wellms cart adapter and
 * against a real Sylius service, so "the adapter works" means the same thing for both and a third provider has a
 * checklist. A test class uses this trait and supplies the five hooks.
 */
trait ProviderContract
{
    abstract protected function contractProvider(): CommerceProvider;

    /** A sellable that exists in this tenant (a course) and may be sold by the provider. */
    abstract protected function contractSellable(): SellableRef;

    /** A price the provider can sell (its channel's or the cart's currency). */
    abstract protected function contractPrice(int $amount): Price;

    /** A paid order of `$user` for `$product`, created the way the provider's own checkout would. Returns the order id. */
    abstract protected function contractPaidOrder(User $user, ProductRef $product): string;

    /** The whole order is refunded at the provider. */
    abstract protected function contractRefund(string $orderId): void;

    abstract protected function contractUser(): User;

    private function contractSpec(int $amount = 4900, bool $active = true, string $name = 'Brewing basics'): ProductSpec
    {
        return new ProductSpec($name, $this->contractPrice($amount), $active, 'A course about brewing.');
    }

    public function testContractSyncReturnsARefOfTheProviderAndRecordsTheLink(): void
    {
        $provider = $this->contractProvider();
        $ref = $this->contractSellable();

        $product = $provider->syncProduct($ref, $this->contractSpec());

        $this->assertSame($provider->key(), $product->provider);
        $this->assertNotSame('', $product->externalId);
        $link = ProductLink::query()->where(['sellable_type' => $ref->type, 'sellable_id' => $ref->id, 'provider' => $provider->key()])->firstOrFail();
        $this->assertSame($product->externalId, $link->external_id);
        $this->assertSame([4900, true], [$link->amount_minor, $link->active]);
        $this->assertNotNull($link->payload_hash);
    }

    public function testContractSyncIsIdempotentAndUpdatesTheSameProduct(): void
    {
        $provider = $this->contractProvider();
        $ref = $this->contractSellable();

        $first = $provider->syncProduct($ref, $this->contractSpec(4900, false));
        $second = $provider->syncProduct($ref, $this->contractSpec(5900, true));
        $third = $provider->syncProduct($ref, $this->contractSpec(5900, true));

        $this->assertSame($first->externalId, $second->externalId);
        $this->assertSame($first->externalId, $third->externalId);
        $this->assertSame(1, ProductLink::query()->where(['sellable_type' => $ref->type, 'sellable_id' => $ref->id, 'provider' => $provider->key()])->count());
        $link = ProductLink::query()->where('provider', $provider->key())->firstOrFail();
        $this->assertSame([5900, true], [$link->amount_minor, $link->active]);
    }

    public function testContractPriceAndActivationAreReadBackFromTheProvider(): void
    {
        $provider = $this->contractProvider();
        if (!$provider instanceof InspectsCatalogue) {
            $this->markTestSkipped('The provider does not inspect its catalogue.');
        }
        $ref = $this->contractSellable();

        $product = $provider->syncProduct($ref, $this->contractSpec(4900, false));
        $state = $provider->inspectProduct($product);
        $this->assertSame([4900, false], [$state->priceMinor, $state->enabled]);

        $provider->syncProduct($ref, $this->contractSpec(6900, true, 'Brewing basics, 2nd edition'));
        $state = $provider->inspectProduct($product);
        $this->assertSame([6900, true, 'Brewing basics, 2nd edition'], [$state->priceMinor, $state->enabled, $state->name]);
        $this->assertSame($this->contractPrice(1)->currency, $state->currency);

        $provider->syncProduct($ref, $this->contractSpec(6900, false, 'Brewing basics, 2nd edition'));
        $this->assertFalse($provider->inspectProduct($product)->enabled, 'unpublishing disables, it does not delete');
    }

    public function testContractAProductThatDoesNotExistIsReportedAsMissing(): void
    {
        $provider = $this->contractProvider();
        if (!$provider instanceof InspectsCatalogue) {
            $this->markTestSkipped('The provider does not inspect its catalogue.');
        }

        $this->assertNull($provider->inspectProduct(new ProductRef($provider->key(), '987654321')));
    }

    public function testContractAPaidOrderIsReadBackAsTheTruthForAGrant(): void
    {
        $provider = $this->contractProvider();
        $user = $this->contractUser();
        $product = $provider->syncProduct($this->contractSellable(), $this->contractSpec());

        $orderId = $this->contractPaidOrder($user, $product);
        $order = $provider->fetchOrder($orderId);

        $this->assertSame($orderId, $order->orderId);
        $this->assertSame($user->getKey(), $order->userRef);
        $this->assertSame(OrderSnapshot::PAYMENT_PAID, $order->paymentState);
        $this->assertNotSame(OrderSnapshot::STATE_CANCELLED, $order->state);
        $this->assertCount(1, $order->items);
        $link = ProductLink::query()->where('provider', $provider->key())
            ->where(fn ($q) => $q->where('external_id', $order->items[0]->productRef)->orWhere('variant_code', $order->items[0]->productRef))->first();
        $this->assertNotNull($link, 'every order line resolves to a product link');
        $this->assertSame($product->externalId, $link->external_id);
        $this->assertGreaterThan(0, $order->totalMinor);
        $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', $order->currency);
    }

    public function testContractARefundedOrderIsReportedAsRefunded(): void
    {
        $provider = $this->contractProvider();
        $product = $provider->syncProduct($this->contractSellable(), $this->contractSpec());
        $orderId = $this->contractPaidOrder($this->contractUser(), $product);

        $this->contractRefund($orderId);

        $this->assertSame(OrderSnapshot::PAYMENT_REFUNDED, $provider->fetchOrder($orderId)->paymentState);
    }

    public function testContractAnUnknownOrderIsAnError(): void
    {
        $this->expectException(\Throwable::class);
        $this->contractProvider()->fetchOrder('987654321');
    }

    public function testContractCheckoutLeadsToAUrl(): void
    {
        $provider = $this->contractProvider();
        $product = $provider->syncProduct($this->contractSellable(), $this->contractSpec());

        $checkout = $provider->createCheckout($this->contractUser(), $product, 'https://shop.test/courses/1');

        $this->assertStringStartsWith('http', $checkout->url);
    }
}
