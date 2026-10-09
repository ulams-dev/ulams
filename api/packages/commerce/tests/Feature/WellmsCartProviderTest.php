<?php

namespace Ulams\Commerce\Tests\Feature;

use InvalidArgumentException;
use Illuminate\Http\Request;
use Ulams\Cart\Facades\Shop;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Tests\Mocks\ExampleProductable;
use Ulams\Commerce\Contracts\CommerceProvider;
use Ulams\Commerce\Models\ProductLink;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\SellableRef;
use Ulams\Commerce\Tests\TestCase;
use Ulams\Core\Models\User;

class WellmsCartProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Shop::registerProductableClass(ExampleProductable::class);
    }

    private function course(): ExampleProductable
    {
        return ExampleProductable::factory()->create(['name' => 'Brewing basics']);
    }

    public function testSyncCreatesAnInactiveSingleProductThroughTheCartService(): void
    {
        $course = $this->course();
        $ref = app(CommerceProvider::class)->syncProduct(SellableRef::course($course->getKey()), new Price(4900, 'USD'), false);

        $product = Product::query()->findOrFail((int) $ref->externalId);
        $this->assertSame('wellms', $ref->provider);
        $this->assertSame(4900, $product->price);
        $this->assertFalse($product->purchasable);
        $this->assertSame('Brewing basics', $product->name);
        $this->assertSame([$course->getKey()], $product->productables->pluck('productable_id')->all());
        $this->assertDatabaseHas('commerce_product_links', ['sellable_type' => 'course', 'sellable_id' => $course->getKey(), 'provider' => 'wellms', 'external_id' => (string) $product->getKey(), 'active' => false]);
    }

    public function testSyncIsIdempotentAndActivationUpdatesTheSameProduct(): void
    {
        $course = $this->course();
        $provider = app(CommerceProvider::class);
        $first = $provider->syncProduct(SellableRef::course($course->getKey()), new Price(4900, 'USD'), false);
        $second = $provider->syncProduct(SellableRef::course($course->getKey()), new Price(5900, 'USD'), true);

        $this->assertSame($first->externalId, $second->externalId);
        $this->assertSame(1, Product::query()->count());
        $this->assertSame(1, ProductLink::query()->count());
        $product = Product::query()->findOrFail((int) $second->externalId);
        $this->assertSame([5900, true], [$product->price, $product->purchasable]);
        $this->assertTrue(ProductLink::query()->first()->active);
    }

    public function testSyncAdoptsAProductAnAdminAlreadyMadeForTheCourse(): void
    {
        $course = $this->course();
        $existing = Product::factory()->create(['price' => 100, 'type' => 'single']);
        $existing->productables()->create(['productable_type' => ExampleProductable::getMorphClassStatic(), 'productable_id' => $course->getKey()]);

        $ref = app(CommerceProvider::class)->syncProduct(SellableRef::course($course->getKey()), new Price(2500, 'USD'), true);

        $this->assertSame((string) $existing->getKey(), $ref->externalId);
        $this->assertSame(2500, $existing->refresh()->price);
    }

    public function testACurrencyThatIsNotTheCartsIsRefused(): void
    {
        config(['ulams_payments.default_currency' => 'USD']);
        $this->expectException(InvalidArgumentException::class);
        app(CommerceProvider::class)->syncProduct(SellableRef::course($this->course()->getKey()), new Price(100, 'EUR'), false);
    }

    public function testAnUnknownSellableFails(): void
    {
        config(['commerce.sellables.course' => null]);
        $this->expectException(InvalidArgumentException::class);
        app(CommerceProvider::class)->syncProduct(SellableRef::course(1), new Price(100, 'USD'), false);
    }

    public function testCheckoutPointsAtTheCartAndThereAreNoOrderWebhooks(): void
    {
        $provider = app(CommerceProvider::class);
        $ref = $provider->syncProduct(SellableRef::course($this->course()->getKey()), new Price(100, 'USD'), true);

        $url = $provider->createCheckout(new User(), $ref, 'https://shop.test/courses/1')->url;

        $this->assertStringStartsWith('https://shop.test/cart?product=' . $ref->externalId, $url);
        $this->assertNull($provider->handleOrderEvent(Request::create('/')));
    }
}
