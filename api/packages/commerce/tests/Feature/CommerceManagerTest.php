<?php

namespace Ulams\Commerce\Tests\Feature;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Ulams\Commerce\CommerceManager;
use Ulams\Commerce\Contracts\CommerceProvider;
use Ulams\Commerce\Support\CheckoutRef;
use Ulams\Commerce\Support\OrderEvent;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\ProductRef;
use Ulams\Commerce\Support\SellableRef;
use Ulams\Commerce\Tests\TestCase;
use Ulams\Core\Models\User;

/** The interface is satisfiable by another backend: a fake provider stands in for Sylius. */
class CommerceManagerTest extends TestCase
{
    private function fake(): CommerceProvider
    {
        return new class () implements CommerceProvider {
            public array $synced = [];

            public function key(): string
            {
                return 'fake';
            }

            public function syncProduct(SellableRef $ref, Price $price, bool $active): ProductRef
            {
                $this->synced[] = [$ref->type, $ref->id, $price->amountMinor, $active];

                return new ProductRef('fake', "{$ref->type}-{$ref->id}");
            }

            public function createCheckout(User $user, ProductRef $product, string $returnUrl): CheckoutRef
            {
                return new CheckoutRef("https://pay.test/{$product->externalId}");
            }

            public function handleOrderEvent(Request $request): ?OrderEvent
            {
                return new OrderEvent('fake', OrderEvent::PAID, (string) $request->input('id'), 'o-1', new ProductRef('fake', 'course-1'), 'a@b.test');
            }
        };
    }

    public function testTheDefaultProviderIsWellms(): void
    {
        $this->assertSame('wellms', app(CommerceProvider::class)->key());
        $this->assertSame(['wellms'], app(CommerceManager::class)->keys());
    }

    public function testAnAdapterRegistersAndIsSelectedByConfig(): void
    {
        $fake = $this->fake();
        app(CommerceManager::class)->extend('fake', fn () => $fake);
        config(['commerce.provider' => 'fake']);

        $provider = app(CommerceProvider::class);
        $ref = $provider->syncProduct(SellableRef::course(3), new Price(1900, 'EUR'), false);

        $this->assertSame('fake', $provider->key());
        $this->assertSame('course-3', $ref->externalId);
        $this->assertSame([['course', 3, 1900, false]], $fake->synced);
        $this->assertSame('https://pay.test/course-3', $provider->createCheckout(new User(), $ref, 'https://x.test')->url);
        $this->assertSame('evt-1', $provider->handleOrderEvent(Request::create('/', 'POST', ['id' => 'evt-1']))->idempotencyKey);
    }

    public function testAnUnknownProviderFails(): void
    {
        config(['commerce.provider' => 'nope']);
        $this->expectException(InvalidArgumentException::class);
        app(CommerceProvider::class);
    }
}
