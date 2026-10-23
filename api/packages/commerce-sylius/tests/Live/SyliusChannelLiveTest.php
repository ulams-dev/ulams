<?php

namespace Ulams\CommerceSylius\Tests\Live;

use Ulams\Commerce\CommerceManager;
use Ulams\Commerce\Services\CatalogueSync;
use Ulams\Commerce\Support\ChannelSpec;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\SellableRef;
use Ulams\CommerceSylius\Http\SyliusException;
use Ulams\CommerceSylius\SyliusProvider;
use Ulams\CommerceSylius\Tests\Support\LiveSylius;
use Ulams\CommerceSylius\Tests\TestCase;

/**
 * A tenant's channel made by `ensureChannel()` on a real Sylius: the EU VAT seed, the payment methods, the bridge link,
 * and tax computed from the billing country in a real checkout. The Stripe method is created with dummy test keys: that
 * Sylius accepts the configuration is checked, a payment at Stripe is not (it needs an account).
 */
class SyliusChannelLiveTest extends TestCase
{
    private LiveSylius $live;
    private string $code;

    protected function setUp(): void
    {
        parent::setUp();
        if (!LiveSylius::available()) {
            $this->markTestSkipped('No real Sylius: set COMMERCE_SYLIUS_LIVE_URL and the live admin credentials (compose profile `commerce`).');
        }
        $this->live = LiveSylius::get();
        $this->code = 'lc' . bin2hex(random_bytes(4));
        config([
            'commerce.channel' => $this->code,
            'commerce-sylius.payments.offline' => true,
            'commerce-sylius.payments.stripe' => ['publishable_key' => 'pk_test_dummy0123456789', 'secret_key' => 'sk_test_dummy0123456789'],
        ] + $this->live->config());
    }

    private function provider(): SyliusProvider
    {
        return app(CommerceManager::class)->provider('sylius');
    }

    private function spec(string $currency = 'EUR', string $seller = 'PL'): ChannelSpec
    {
        return new ChannelSpec($this->code, 'Live channel', $currency, 'en_US', ['PL', 'DE'], $seller, "{$this->code}.localhost", "http://{$this->code}.localhost/api/commerce/webhooks/sylius", 'whsec_live_0123456789abcdef0123456789');
    }

    public function testTheChannelIsCreatedWithTheSeedTheMethodsAndTheLinkAndRepeatingItChangesNothing(): void
    {
        (new \Ulams\CommerceSylius\EuVatSeeder($this->live->admin))->seed();
        $ref = $this->provider()->ensureChannel($this->spec());
        $this->assertTrue($ref->created);

        $a = $this->live->admin;
        $channel = $a->get("/api/v2/admin/channels/{$this->code}");
        $this->assertSame(['/api/v2/admin/currencies/EUR', '/api/v2/admin/locales/en_US', '/api/v2/admin/zones/eu-pl', "{$this->code}.localhost"], [$channel['baseCurrency'], $channel['defaultLocale'], $channel['defaultTaxZone'], $channel['hostname']]);
        $this->assertCount(27, $channel['countries']);
        $this->assertSame(0.23, $a->get('/api/v2/admin/tax-rates/vat-pl')['amount']);
        $this->assertTrue($a->get('/api/v2/admin/tax-rates/vat-de')['includedInPrice']);
        foreach (['offline', 'stripe'] as $method) {
            $this->assertContains("/api/v2/admin/channels/{$this->code}", $a->get("/api/v2/admin/payment-methods/{$method}")['channels'], $method);
        }
        $stripe = $a->get('/api/v2/admin/payment-methods/stripe');
        $this->assertSame('stripe_checkout', $stripe['gatewayConfig']['factoryName']);
        $link = $a->get("/api/v2/admin/ulams/channel-links/{$this->code}");
        $this->assertSame("http://{$this->code}.localhost/api/commerce/webhooks/sylius", $link['webhookUrl']);
        $this->assertArrayNotHasKey('secret', $link, 'the secret is write-only');
        $this->assertStringNotContainsString('whsec_live', (string) json_encode($link));

        $again = $this->provider()->ensureChannel($this->spec());
        $this->assertFalse($again->created);
        $this->assertSame($channel['baseCurrency'], $a->get("/api/v2/admin/channels/{$this->code}")['baseCurrency']);
    }

    public function testTheBaseCurrencyCannotBeMovedAndTheChannelIsLeftAlone(): void
    {
        $this->provider()->ensureChannel($this->spec());

        $this->expectException(SyliusException::class);
        $this->expectExceptionMessage('base currency cannot change');
        $this->provider()->ensureChannel($this->spec('PLN'));
    }

    public function testTheBridgeRefusesALinkToAnythingButTheLmsWebhookPath(): void
    {
        $this->provider()->ensureChannel($this->spec());

        try {
            $this->live->admin->put("/api/v2/admin/ulams/channel-links/{$this->code}", ['webhookUrl' => 'https://evil.example/internal', 'secret' => 'whsec_live_0123456789abcdef0123456789']);
            $this->fail('expected a refusal');
        } catch (SyliusException $e) {
            $this->assertSame(422, $e->status);
        }
        $this->assertStringEndsWith('/api/commerce/webhooks/sylius', $this->live->admin->get("/api/v2/admin/ulams/channel-links/{$this->code}")['webhookUrl']);
    }

    public function testVatIsTakenFromTheBillingCountryAndIsIncludedInThePrice(): void
    {
        (new \Ulams\CommerceSylius\EuVatSeeder($this->live->admin))->seed();
        $this->provider()->ensureChannel($this->spec());
        $course = $this->course();
        $ref = SellableRef::course($course->getKey());
        app(CatalogueSync::class)->setPrice($ref, new Price(12300, 'EUR'));
        app(CatalogueSync::class)->push($ref);
        $variant = strtolower("ul-{$this->code}-course-{$course->getKey()}") . '-v';
        $buy = fn (string $country) => $this->live->buy($this->live->customer($this->user()->getKey()), $variant, $this->code, 'offline', $country);

        $pl = $this->live->admin->get('/api/v2/admin/orders/' . $buy('PL')['token']);
        $de = $this->live->admin->get('/api/v2/admin/orders/' . $buy('DE')['token']);

        $this->assertSame([12300, 2300], [$pl['total'], $pl['taxIncludedTotal']], '23% of the gross price, included');
        $this->assertSame([12300, 1964], [$de['total'], $de['taxIncludedTotal']], '19% for a German buyer');
    }

    public function testADisabledChannelStaysWithItsData(): void
    {
        $this->provider()->ensureChannel($this->spec());

        $this->provider()->disableChannel($this->code);

        $this->assertFalse($this->live->admin->get("/api/v2/admin/channels/{$this->code}")['enabled']);
        $this->assertNotNull($this->live->admin->get("/api/v2/admin/ulams/channel-links/{$this->code}"));
    }
}
