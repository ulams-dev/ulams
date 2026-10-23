<?php

namespace Ulams\CommerceSylius\Tests\Live;

use Ulams\Commerce\CommerceManager;
use Ulams\Commerce\Contracts\CommerceProvider;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\ProductRef;
use Ulams\Commerce\Support\SellableRef;
use Ulams\Commerce\Tests\Support\ProviderContract;
use Ulams\CommerceSylius\Tests\Support\LiveSylius;
use Ulams\CommerceSylius\Tests\TestCase;
use Ulams\Core\Models\User;

/**
 * The provider contract against a real Sylius 2.x (skipped without COMMERCE_SYLIUS_LIVE_URL). The same checklist runs
 * against the Wellms cart adapter in the `commerce` package.
 */
class SyliusProviderContractTest extends TestCase
{
    use ProviderContract;

    private LiveSylius $live;
    /** @var array<string,string> order token => payment id */
    private array $payments = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (!LiveSylius::available()) {
            $this->markTestSkipped('No real Sylius: set COMMERCE_SYLIUS_LIVE_URL and the live admin credentials (compose profile `commerce`).');
        }
        $this->live = LiveSylius::get();
        config($this->live->config());
    }

    protected function contractProvider(): CommerceProvider
    {
        return app(CommerceManager::class)->provider('sylius');
    }

    protected function contractSellable(): SellableRef
    {
        return SellableRef::course($this->course()->getKey());
    }

    protected function contractPrice(int $amount): Price
    {
        return new Price($amount, 'EUR');
    }

    protected function contractUser(): User
    {
        return $this->user();
    }

    protected function contractPaidOrder(User $user, ProductRef $product): string
    {
        $order = $this->live->paidOrder($user->getKey(), $product->externalId . '-v');
        $this->payments[$order['token']] = $order['payment'];

        return $order['token'];
    }

    protected function contractRefund(string $orderId): void
    {
        $this->live->refund($this->payments[$orderId]);
    }
}
