<?php

namespace Ulams\CommerceSylius\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Ulams\Commerce\CommerceManager;
use Ulams\Commerce\Models\ProductLink;
use Ulams\Commerce\Support\OrderSnapshot;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\ProductRef;
use Ulams\Commerce\Support\ProductSpec;
use Ulams\Commerce\Support\SellableRef;
use Ulams\CommerceSylius\Http\SyliusException;
use Ulams\CommerceSylius\SyliusProvider;
use Ulams\CommerceSylius\Tests\Support\FakeSylius;
use Ulams\CommerceSylius\Tests\TestCase;

/** The adapter against an in-memory Sylius Admin API (the shapes were recorded from Sylius 2.3). */
class SyliusProviderTest extends TestCase
{
    private FakeSylius $sylius;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sylius = (new FakeSylius())->install();
    }

    private function provider(): SyliusProvider
    {
        $provider = app(CommerceManager::class)->provider();
        $this->assertInstanceOf(SyliusProvider::class, $provider);

        return $provider;
    }

    private function spec(int $amount = 4900, bool $active = true, string $currency = 'EUR'): ProductSpec
    {
        return new ProductSpec('Brewing basics', new Price($amount, $currency), $active, 'Learn to brew.');
    }

    public function testTheProviderIsSelectedByConfigAndKnowsItsKey(): void
    {
        $this->assertSame('sylius', $this->provider()->key());
        $this->assertContains('sylius', app(CommerceManager::class)->keys());
    }

    public function testSyncCreatesAProductAndAVariantWithoutShippingAndRecordsTheLink(): void
    {
        $course = $this->course();
        $ref = $this->provider()->syncProduct(SellableRef::course($course->getKey()), $this->spec(active: false));

        $code = "ul-acme-course-{$course->getKey()}";
        $this->assertEquals(new ProductRef('sylius', $code), $ref);
        $product = $this->sylius->products[$code];
        $this->assertFalse($product['enabled']);
        $this->assertSame(['/api/v2/admin/channels/acme'], $product['channels']);
        $this->assertSame('Brewing basics', $product['translations']['en_US']['name']);
        $variant = $this->sylius->variants["{$code}-v"];
        $this->assertFalse($variant['shippingRequired']);
        $this->assertFalse($variant['tracked']);
        $this->assertSame(4900, $variant['channelPricings']['acme']['price']);
        $this->assertSame('/api/v2/admin/tax-categories/e-service', $variant['taxCategory']);
        $link = ProductLink::query()->where('provider', 'sylius')->firstOrFail();
        $this->assertSame([$code, "{$code}-v", 'acme', 4900, false], [$link->external_id, $link->variant_code, $link->channel_code, $link->amount_minor, $link->active]);
        $this->assertNotNull($link->synced_at);
        $this->assertNull($link->sync_error);
    }

    public function testSyncIsIdempotentAndUpdatesTheSameProductInPlace(): void
    {
        $course = $this->course();
        $first = $this->provider()->syncProduct(SellableRef::course($course->getKey()), $this->spec(4900, false));
        $second = $this->provider()->syncProduct(SellableRef::course($course->getKey()), $this->spec(5900, true));

        $this->assertSame($first->externalId, $second->externalId);
        $this->assertCount(1, $this->sylius->products);
        $this->assertCount(1, $this->sylius->variants);
        $this->assertTrue($this->sylius->products[$first->externalId]['enabled']);
        $this->assertSame(5900, $this->sylius->variants["{$first->externalId}-v"]['channelPricings']['acme']['price']);
        $this->assertSame(1, ProductLink::query()->count());
        $posts = array_filter($this->sylius->writes(), fn ($w) => $w[0] === 'POST');
        $this->assertCount(2, $posts, 'one POST for the product and one for the variant, only on the first sync');
    }

    public function testUnpublishingDisablesTheProductAndNeverDeletesIt(): void
    {
        $course = $this->course();
        $ref = $this->provider()->syncProduct(SellableRef::course($course->getKey()), $this->spec(active: true));
        $this->provider()->syncProduct(SellableRef::course($course->getKey()), $this->spec(active: false));

        $this->assertFalse($this->sylius->products[$ref->externalId]['enabled']);
        $this->assertFalse($this->sylius->variants["{$ref->externalId}-v"]['enabled']);
        $this->assertSame([], array_filter($this->sylius->writes(), fn ($w) => $w[0] === 'DELETE'));
    }

    public function testEverySellableTypeGetsItsOwnProductCode(): void
    {
        $provider = $this->provider();

        $this->assertSame('ul-acme-course-12', $provider->productCode(SellableRef::course(12)));
        $this->assertSame('ul-acme-bundle-3', $provider->productCode(SellableRef::bundle(3)));
        $this->assertSame('ul-acme-access-pass-7', $provider->productCode(SellableRef::accessPass(7)));
    }

    public function testAPriceInAnotherCurrencyThanTheChannelsIsRefusedBeforeAnythingIsWritten(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sells in EUR');
        try {
            $this->provider()->syncProduct(SellableRef::course($this->course()->getKey()), $this->spec(currency: 'PLN'));
        } finally {
            $this->assertSame([], $this->sylius->writes());
        }
    }

    public function testAMissingChannelIsAnErrorNotASilentSkip(): void
    {
        $this->sylius->channelExists = false;

        $this->expectException(SyliusException::class);
        $this->expectExceptionMessage('does not exist');
        $this->provider()->syncProduct(SellableRef::course($this->course()->getKey()), $this->spec());
    }

    public function testATenantWithoutAChannelCannotUseTheProvider(): void
    {
        config(['commerce.channel' => null]);

        $this->expectException(\RuntimeException::class);
        $this->provider()->syncProduct(SellableRef::course($this->course()->getKey()), $this->spec());
    }

    public function testTheTokenIsCachedAndRenewedOnceWhenSylviusAnswers401(): void
    {
        $course = $this->course();
        $this->provider()->syncProduct(SellableRef::course($course->getKey()), $this->spec());
        $this->assertSame(1, $this->sylius->tokenRequests, 'one sign-in serves many calls');

        $this->sylius->unauthorizedOnce = 1;
        $this->provider()->syncProduct(SellableRef::course($course->getKey()), $this->spec(5000));

        $this->assertSame(2, $this->sylius->tokenRequests);
        $this->assertSame(5000, $this->sylius->variants["ul-acme-course-{$course->getKey()}-v"]['channelPricings']['acme']['price']);
    }

    public function testErrorsNeverCarryTheCredentialsOrAToken(): void
    {
        $this->sylius->unauthorizedOnce = 5;
        try {
            $this->provider()->syncProduct(SellableRef::course($this->course()->getKey()), $this->spec());
            $this->fail('expected a failure');
        } catch (SyliusException $e) {
            $this->assertSame(401, $e->status);
            $this->assertStringNotContainsString('not-a-real-password', $e->getMessage());
            $this->assertStringNotContainsString('jwt-', $e->getMessage());
        }
    }

    public function testAnUnreachableSyliusIsAnExceptionTheJobRetries(): void
    {
        $this->sylius->down = true;

        $this->expectException(SyliusException::class);
        $this->expectExceptionMessage('unreachable');
        $this->provider()->fetchOrder('abc');
    }

    public function testFetchOrderMapsTheOrderAndLinksTheCustomerOfThisChannelOnly(): void
    {
        $user = $this->user();
        $this->sylius->order('tok-1', ['ul-acme-course-1-v'], $user->getKey());
        $this->sylius->order('tok-2', ['ul-acme-course-1-v'], $user->getKey(), ref: "globex:{$user->getKey()}");
        $this->sylius->order('tok-3', ['ul-acme-course-1-v'], null);

        $paid = $this->provider()->fetchOrder('tok-1');
        $this->assertSame(['tok-1', 'acme', $user->getKey(), 'new', 'paid', 'EUR', 4900], [$paid->orderId, $paid->channel, $paid->userRef, $paid->state, $paid->paymentState, $paid->currency, $paid->totalMinor]);
        $this->assertSame('ul-acme-course-1-v', $paid->items[0]->productRef);
        $this->assertNull($this->provider()->fetchOrder('tok-2')->userRef, 'a customer linked in another tenant is not this tenant\'s user');
        $this->assertNull($this->provider()->fetchOrder('tok-3')->userRef);
    }

    public function testFetchOrderOfAnotherChannelIsReportedWithThatChannelAndNoUser(): void
    {
        $this->sylius->order('tok-g', ['ul-globex-course-1-v'], 5, channel: 'globex');

        $order = $this->provider()->fetchOrder('tok-g');

        $this->assertSame('globex', $order->channel);
        $this->assertNull($order->userRef);
    }

    public function testAnUnknownOrderFails(): void
    {
        $this->expectException(SyliusException::class);
        $this->provider()->fetchOrder('missing');
    }

    public function testARecordedSyliusOrderIsUnderstood(): void
    {
        $order = $this->fixture('sylius-order-refunded');
        $this->sylius->orders[$order['tokenValue']] = $order;
        $customer = $this->fixture('sylius-customer');
        $customer['ulamsUserRef'] = 'acme:41';
        $this->sylius->customers['2'] = $customer;

        $snapshot = $this->provider()->fetchOrder($order['tokenValue']);

        $this->assertSame([41, 'acme', OrderSnapshot::PAYMENT_REFUNDED, 'EUR', 14700], [$snapshot->userRef, $snapshot->channel, $snapshot->paymentState, $snapshot->currency, $snapshot->totalMinor]);
        $this->assertSame('ul-acme-course-1-v', $snapshot->items[0]->productRef);
        $this->assertSame('000000002', $snapshot->number);
    }

    public function testTheFeedIsReadFromACursorAndMalformedEventsAreSkipped(): void
    {
        $this->sylius->feed = [
            ['version' => 'v1', 'id' => 'e1', 'type' => 'order.paid', 'channel_code' => 'acme', 'order_id' => 'tok-1', 'sequence' => 1],
            ['version' => 'v9', 'id' => 'e2', 'type' => 'order.paid', 'channel_code' => 'acme', 'order_id' => 'tok-1', 'sequence' => 2],
            ['version' => 'v1', 'id' => 'e3', 'type' => 'order.refunded', 'channel_code' => 'acme', 'order_id' => 'tok-1', 'sequence' => 3],
        ];

        $page = $this->provider()->orderEventsAfter(null, 200);
        $this->assertSame(['e1', 'e3'], array_map(fn ($e) => $e->eventId, $page->events));
        $this->assertSame('3', $page->nextCursor);
        $this->assertSame([], $this->provider()->orderEventsAfter('3', 200)->events);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/ulams/events') && str_contains($r->url(), 'channel=acme'));
    }

    public function testInspectReportsPriceStateAndNullForAMissingProduct(): void
    {
        $course = $this->course();
        $ref = $this->provider()->syncProduct(SellableRef::course($course->getKey()), $this->spec(4900, true));

        $state = $this->provider()->inspectProduct($ref);
        $this->assertSame(['Brewing basics', 4900, 'EUR', true], [$state->name, $state->priceMinor, $state->currency, $state->enabled]);
        $this->assertNull($this->provider()->inspectProduct(new ProductRef('sylius', 'ul-acme-course-999')));
    }

    public function testOrdersChangedSinceListsOnlyPaidRefundedAndCancelledOrdersAcrossPages(): void
    {
        $user = $this->user();
        for ($i = 1; $i <= 31; $i++) {
            $this->sylius->order("p{$i}", ['ul-acme-course-1-v'], $user->getKey());
        }
        $this->sylius->order('unpaid', ['ul-acme-course-1-v'], $user->getKey(), 'awaiting_payment');
        $this->sylius->order('refunded', ['ul-acme-course-1-v'], $user->getKey(), 'refunded');
        $this->sylius->order('cancelled', ['ul-acme-course-1-v'], $user->getKey(), 'awaiting_payment', 'cancelled');

        $tokens = [];
        foreach ($this->provider()->ordersChangedSince(now()->subDays(35)) as $order) {
            $tokens[] = $order->orderId;
        }

        $this->assertCount(31 + 2, $tokens);
        $this->assertNotContains('unpaid', $tokens);
        $this->assertContains('refunded', $tokens);
        $this->assertContains('cancelled', $tokens);
    }

    public function testCheckoutPointsAtTheFrontendCheckout(): void
    {
        $url = $this->provider()->createCheckout($this->user(), new ProductRef('sylius', 'ul-acme-course-5'), 'https://acme.test/courses/5')->url;

        $this->assertStringStartsWith('https://acme.test/checkout/start?product=ul-acme-course-5', $url);
        $this->assertNull($this->provider()->handleOrderEvent(\Illuminate\Http\Request::create('/')));
    }
}
