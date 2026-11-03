<?php

namespace Ulams\CommerceSylius\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Ulams\Commerce\CommerceManager;
use Ulams\Commerce\Support\ChannelSpec;
use Ulams\CommerceSylius\EuVatSeeder;
use Ulams\CommerceSylius\Http\SyliusClient;
use Ulams\CommerceSylius\Http\SyliusException;
use Ulams\CommerceSylius\SyliusChannelProvisioner;
use Ulams\CommerceSylius\SyliusProvider;
use Ulams\CommerceSylius\Tests\Support\FakeSylius;
use Ulams\CommerceSylius\Tests\TestCase;
use Ulams\Tenancy\Models\Tenant;

/** A tenant's channel, the EU VAT seed and the payment methods, against an in-memory Sylius. */
class ChannelProvisioningTest extends TestCase
{
    private FakeSylius $sylius;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sylius = (new FakeSylius())->install();
        $this->sylius->channelExists = false;
        config(['commerce-sylius.payments.offline' => false, 'commerce-sylius.payments.stripe' => ['publishable_key' => null, 'secret_key' => null]]);
    }

    private function provider(): SyliusProvider
    {
        return app(CommerceManager::class)->provider('sylius');
    }

    private function spec(string $code = 'acme', string $currency = 'EUR', string $locale = 'en_US', string $seller = 'PL'): ChannelSpec
    {
        return new ChannelSpec($code, 'Acme Academy', $currency, $locale, ['PL', 'DE', 'GB'], $seller, 'acme.example', 'https://api.acme.example/api/commerce/webhooks/sylius', 'whsec_0123456789abcdef0123456789abcdef');
    }

    public function testSpecsAreValidated(): void
    {
        foreach ([['a b', 'EUR', 'en_US'], ['acme', 'euro', 'en_US'], ['acme', 'EUR', 'english']] as [$code, $currency, $locale]) {
            try {
                new ChannelSpec($code, 'x', $currency, $locale, ['PL'], 'PL');
                $this->fail("{$code} {$currency} {$locale} should be refused");
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        new ChannelSpec('acme', 'x', 'EUR', 'en_US', ['PL'], 'PL', null, 'https://x.test/hook', null);
    }

    public function testTheChannelIsCreatedWithCurrencyLocaleCountriesTaxZoneAndHostname(): void
    {
        $ref = $this->provider()->ensureChannel($this->spec());

        $this->assertSame(['sylius', 'acme', true], [$ref->provider, $ref->code, $ref->created]);
        $channel = $this->sylius->channelsStore['acme'];
        $this->assertSame('/api/v2/admin/currencies/EUR', $channel['baseCurrency']);
        $this->assertSame('/api/v2/admin/locales/en_US', $channel['defaultLocale']);
        $this->assertSame('/api/v2/admin/zones/eu-pl', $channel['defaultTaxZone']);
        $this->assertSame('order_items_based', $channel['taxCalculationStrategy']);
        $this->assertTrue($channel['skippingShippingStepAllowed']);
        $this->assertSame('acme.example', $channel['hostname']);
        $this->assertContains('/api/v2/admin/countries/GB', $channel['countries']);
        $this->assertContains('/api/v2/admin/countries/FR', $channel['countries'], 'all 27 member states');
        $this->assertCount(28, $channel['countries'], '27 member states and GB');
        $this->assertArrayHasKey('EUR', $this->sylius->resources['currencies']);
        $this->assertArrayHasKey('en_US', $this->sylius->resources['locales']);
    }

    public function testTheBridgeLinkIsSetWithTheTenantsUrlAndSecret(): void
    {
        $this->provider()->ensureChannel($this->spec());

        $this->assertSame(['webhookUrl' => 'https://api.acme.example/api/commerce/webhooks/sylius', 'secret' => 'whsec_0123456789abcdef0123456789abcdef'], $this->sylius->links['acme']);
    }

    public function testRunningItAgainRepairsAndCreatesNothingNew(): void
    {
        $this->provider()->ensureChannel($this->spec());
        // an operator added a currency by hand and the hostname drifted
        $this->sylius->channelsStore['acme']['currencies'][] = '/api/v2/admin/currencies/USD';
        $this->sylius->channelsStore['acme']['hostname'] = 'wrong.example';

        $ref = $this->provider()->ensureChannel($this->spec());

        $this->assertFalse($ref->created);
        $channel = $this->sylius->channelsStore['acme'];
        $this->assertSame('acme.example', $channel['hostname']);
        $this->assertContains('/api/v2/admin/currencies/USD', $channel['currencies'], 'what an operator added stays');
        $this->assertCount(28, array_unique($channel['countries']));
    }

    public function testTheBaseCurrencyCannotBeChangedAndIsReportedInsteadOfBeingTouched(): void
    {
        $this->provider()->ensureChannel($this->spec());

        try {
            $this->provider()->ensureChannel($this->spec(currency: 'PLN'));
            $this->fail('expected a refusal');
        } catch (SyliusException $e) {
            $this->assertStringContainsString('base currency cannot change', $e->getMessage());
            $this->assertSame(409, $e->status);
        }
        $this->assertSame('/api/v2/admin/currencies/EUR', $this->sylius->channelsStore['acme']['baseCurrency']);
    }

    public function testTwoTenantsGetTwoChannelsAndTheirOwnLinks(): void
    {
        $this->provider()->ensureChannel($this->spec('acme'));
        $this->provider()->ensureChannel($this->spec('globex', 'PLN', 'pl_PL'));

        $this->assertSame(['acme', 'globex'], array_keys($this->sylius->channelsStore));
        $this->assertSame(['acme', 'globex'], array_keys($this->sylius->links));
        $this->assertSame('/api/v2/admin/currencies/PLN', $this->sylius->channelsStore['globex']['baseCurrency']);
    }

    public function testTheEuVatSeedCreatesCountriesZonesAndRatesAndOnlyCorrectsWhatDiffers(): void
    {
        $seeder = new EuVatSeeder(app(SyliusClient::class));

        $first = $seeder->seed();
        $this->assertSame(['created' => 27 * 3, 'updated' => 0, 'unchanged' => 1], $first);
        $this->assertSame(0.23, $this->sylius->resources['tax-rates']['vat-pl']['amount']);
        $this->assertSame(0.255, $this->sylius->resources['tax-rates']['vat-fi']['amount']);
        $this->assertTrue($this->sylius->resources['tax-rates']['vat-de']['includedInPrice']);
        $this->assertSame('/api/v2/admin/zones/eu-de', $this->sylius->resources['tax-rates']['vat-de']['zone']);
        $this->assertSame('/api/v2/admin/tax-categories/e-service', $this->sylius->resources['tax-rates']['vat-de']['category']);
        $this->assertSame([['code' => 'DE']], $this->sylius->resources['zones']['eu-de']['members']);

        $this->sylius->resources['tax-rates']['vat-pl']['amount'] = 0.20;
        $this->sylius->resources['countries']['FR']['enabled'] = false;
        $second = $seeder->seed();

        $this->assertSame(['created' => 0, 'updated' => 2, 'unchanged' => 1 + 27 * 3 - 2], $second);
        $this->assertSame(0.23, $this->sylius->resources['tax-rates']['vat-pl']['amount']);
        $this->assertTrue($this->sylius->resources['countries']['FR']['enabled']);
        $this->assertSame(['created' => 0, 'updated' => 0, 'unchanged' => 1 + 27 * 3], $seeder->seed());
    }

    public function testTheRatesFileIsCompleteAndMarkedUnconfirmed(): void
    {
        $data = (new EuVatSeeder(app(SyliusClient::class)))->data();

        $this->assertFalse($data['confirmed'], 'rates are seed data until an accountant confirms them');
        $this->assertCount(27, $data['rates']);
        $this->assertSame(['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'], collect(array_keys($data['rates']))->sort()->values()->all());
        foreach ($data['rates'] as $country => $rate) {
            $this->assertGreaterThanOrEqual(17, $rate, $country);
            $this->assertLessThanOrEqual(27, $rate, $country);
        }
        $this->assertStringStartsWith('https://', $data['source']);
    }

    public function testTheSeedCommandReportsAndWarnsAboutUnconfirmedRates(): void
    {
        Artisan::call('ulams:commerce:seed-eu-vat');
        $out = Artisan::output();

        $this->assertStringContainsString('81 created', $out);
        $this->assertStringContainsString('UNCONFIRMED', $out);
        $this->assertSame(0, Artisan::call('ulams:commerce:seed-eu-vat'));
        $this->assertStringContainsString('0 created', Artisan::output());
    }

    public function testASellerCountryOutsideTheSeedIsSeededToo(): void
    {
        $this->sylius->channelsStore = [];

        $this->provider()->ensureChannel($this->spec(seller: 'GB'));

        $this->assertArrayHasKey('GB', $this->sylius->resources['countries']);
    }

    public function testStripeIsCreatedOnlyWithKeysAndGainsEveryChannelWithoutLosingTheOthers(): void
    {
        $this->provider()->ensureChannel($this->spec('acme'));
        $this->assertArrayNotHasKey('stripe', $this->sylius->resources['payment-methods'] ?? [], 'no keys, no Stripe');

        config(['commerce-sylius.payments.stripe' => ['publishable_key' => 'pk_test_dummydummy1234', 'secret_key' => 'sk_test_dummydummy1234']]);
        $this->provider()->ensureChannel($this->spec('acme'));
        $this->provider()->ensureChannel($this->spec('globex'));

        $stripe = $this->sylius->resources['payment-methods']['stripe'];
        $this->assertSame('stripe_checkout', $stripe['gatewayConfig']['factoryName']);
        $this->assertSame(['pk_test_dummydummy1234', 'sk_test_dummydummy1234'], [$stripe['gatewayConfig']['config']['publishable_key'], $stripe['gatewayConfig']['config']['secret_key']]);
        $this->assertEqualsCanonicalizing(['/api/v2/admin/channels/acme', '/api/v2/admin/channels/globex'], $stripe['channels']);
        $this->assertTrue($stripe['enabled']);
    }

    public function testRotatedStripeKeysAreWrittenToTheExistingMethod(): void
    {
        config(['commerce-sylius.payments.stripe' => ['publishable_key' => 'pk_test_old', 'secret_key' => 'sk_test_old']]);
        $this->provider()->ensureChannel($this->spec('acme'));
        config(['commerce-sylius.payments.stripe' => ['publishable_key' => 'pk_test_new', 'secret_key' => 'sk_test_new']]);

        $this->provider()->ensureChannel($this->spec('acme'));

        $this->assertSame('sk_test_new', $this->sylius->resources['payment-methods']['stripe']['gatewayConfig']['config']['secret_key']);
    }

    public function testTheOfflineMethodIsForDevelopmentOnly(): void
    {
        $this->provider()->ensureChannel($this->spec());
        $this->assertArrayNotHasKey('offline', $this->sylius->resources['payment-methods'] ?? []);

        config(['commerce-sylius.payments.offline' => true]);
        $this->provider()->ensureChannel($this->spec());
        $this->assertSame('offline', $this->sylius->resources['payment-methods']['offline']['gatewayConfig']['factoryName']);
    }

    public function testDisablingAChannelKeepsItAndItsProducts(): void
    {
        $this->provider()->ensureChannel($this->spec());

        $this->provider()->disableChannel('acme');
        $this->provider()->disableChannel('never-existed');

        $this->assertFalse($this->sylius->channelsStore['acme']['enabled']);
        $this->assertSame([], array_filter($this->sylius->writes(), fn ($w) => $w[0] === 'DELETE'));
        $this->assertArrayNotHasKey('never-existed', $this->sylius->channelsStore);
    }

    public function testTheTenantProvisionerBuildsTheSpecFromTheTenantAndPlatformSettings(): void
    {
        config(['commerce.provider' => 'sylius', 'app.locale' => 'pl', 'commerce-sylius.seller_country' => 'PL', 'commerce-sylius.extra_countries' => 'gb, ch', 'commerce-sylius.default_currency' => null]);
        config(['ulams_tenancy.inherited_env' => ['COMMERCE_CURRENCY' => null, 'COMMERCE_LOCALE' => null]]);
        $tenant = new Tenant(['slug' => 'acme', 'name' => 'Acme Academy', 'front_host' => 'acme.app.example']);
        $provisioner = app(SyliusChannelProvisioner::class);

        $spec = $provisioner->spec($tenant, 'https://api.acme.example/api/commerce/webhooks/sylius', 'whsec_x_0123456789abcdef0123456789');

        $this->assertSame(['acme', 'Acme Academy', 'PLN', 'pl_PL', 'PL', 'acme.app.example'], [$spec->code, $spec->name, $spec->currency, $spec->locale, $spec->sellerCountry, $spec->hostname]);
        $this->assertContains('CH', $spec->countries);
        $this->assertCount(29, $spec->countries);

        config(['app.locale' => 'en']);
        $this->assertSame(['EUR', 'en_US'], [($s = $provisioner->spec($tenant, 'https://a.example/api/commerce/webhooks/sylius', 'whsec_x_0123456789abcdef0123456789'))->currency, $s->locale]);

        $tenant->env_overrides = ['COMMERCE_CURRENCY' => 'usd', 'COMMERCE_LOCALE' => 'de_DE'];
        $this->assertSame(['USD', 'de_DE'], [($s = $provisioner->spec($tenant, 'https://a.example/api/commerce/webhooks/sylius', 'whsec_x_0123456789abcdef0123456789'))->currency, $s->locale]);
    }

    public function testTheTenantProvisionerIsOnlyEnabledForSyliusWithCredentials(): void
    {
        $provisioner = app(SyliusChannelProvisioner::class);

        config(['commerce.provider' => 'wellms']);
        $this->assertFalse($provisioner->enabled());
        config(['commerce.provider' => 'sylius']);
        $this->assertTrue($provisioner->enabled());
        config(['commerce-sylius.admin.password' => null]);
        $this->assertFalse(app(SyliusChannelProvisioner::class)->enabled());
    }
}
