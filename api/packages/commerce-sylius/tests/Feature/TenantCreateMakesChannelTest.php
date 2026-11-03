<?php

namespace Ulams\CommerceSylius\Tests\Feature;

use Illuminate\Support\Facades\File;
use Mockery;
use Ulams\CommerceSylius\Tests\Support\FakeSylius;
use Ulams\CommerceSylius\UlamsCommerceSyliusServiceProvider;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\BucketProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DatabaseProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Tests\Mocks\FakeTenantCommandRunner;
use Ulams\Tenancy\Tests\TestCase;

/**
 * The whole chain, `ulams:tenant:create` through the real Sylius provisioner, against an in-memory Sylius: the tenant ends
 * up with a channel (currency, locale, tax zone, link to its own webhook URL and secret) and its env file knows both.
 */
class TenantCreateMakesChannelTest extends TestCase
{
    private string $storage;
    private FakeSylius $sylius;
    /** @var array<string, array<string, string>> */
    private array $envFiles = [];

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), UlamsCommerceSyliusServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = sys_get_temp_dir() . '/ulams-sylius-tenant-' . uniqid();
        $this->sylius = (new FakeSylius())->install();
        $this->sylius->channelExists = false;
        config([
            'commerce.provider' => 'sylius', 'commerce-sylius.url' => 'http://sylius.test', 'commerce-sylius.admin.email' => 'lms@sylius.test',
            'commerce-sylius.admin.password' => 'not-a-real-password', 'commerce-sylius.payments.offline' => true, 'app.locale' => 'pl',
            'commerce-sylius.seller_country' => 'PL', 'commerce-sylius.default_currency' => null,
        ]);
        $database = Mockery::mock(DatabaseProvisionerContract::class);
        $database->shouldReceive('ensure', 'drop');
        $buckets = Mockery::mock(BucketProvisionerContract::class);
        $buckets->shouldReceive('ensure', 'delete');
        $domains = Mockery::mock(DomainRegistryContract::class);
        $domains->shouldReceive('storagePath')->andReturn($this->storage);
        $domains->shouldReceive('add')->andReturnUsing(function (string $host, array $values) {
            $this->envFiles[$host] = $values;
        });
        $domains->shouldReceive('remove');
        $this->app->instance(TenantCommandRunnerContract::class, new FakeTenantCommandRunner($this->storage));
        $this->app->instance(DatabaseProvisionerContract::class, $database);
        $this->app->instance(BucketProvisionerContract::class, $buckets);
        $this->app->instance(DomainRegistryContract::class, $domains);
        $this->app->forgetInstance(\Ulams\Tenancy\Services\TenantProvisioner::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    public function testCreatingATenantMakesItsChannelWithCurrencyLocaleTaxZoneAndLink(): void
    {
        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--users' => 0])->assertExitCode(0);

        $tenant = Tenant::query()->firstWhere('slug', 'acme');
        $channel = $this->sylius->channelsStore['acme'];
        $this->assertSame(['/api/v2/admin/currencies/PLN', '/api/v2/admin/locales/pl_PL', '/api/v2/admin/zones/eu-pl'], [$channel['baseCurrency'], $channel['defaultLocale'], $channel['defaultTaxZone']], 'Polish-language tenants sell in PLN (plan Q2)');
        $this->assertSame($tenant->front_host, $channel['hostname']);
        $this->assertSame("http://{$tenant->api_host}/api/commerce/webhooks/sylius", $this->sylius->links['acme']['webhookUrl']);
        $this->assertSame($tenant->commerce_webhook_secret, $this->sylius->links['acme']['secret']);
        $this->assertSame(['acme', $tenant->commerce_webhook_secret], [$this->envFiles[$tenant->api_host]['COMMERCE_CHANNEL'], $this->envFiles[$tenant->api_host]['COMMERCE_WEBHOOK_SECRET']]);
        $this->assertSame('offline', $this->sylius->resources['payment-methods']['offline']['gatewayConfig']['factoryName']);
        $this->assertSame(0.23, $this->sylius->resources['tax-rates']['vat-pl']['amount']);
    }

    public function testATenantCreatedBeforeCommerceWasOnGetsItsChannelFromTheUpgradeCommand(): void
    {
        config(['commerce.provider' => 'wellms']);
        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--users' => 0])->assertExitCode(0);
        $this->assertSame([], $this->sylius->channelsStore);

        config(['commerce.provider' => 'sylius', 'app.locale' => 'en']);
        $this->artisan('ulams:tenant:commerce-channel')->assertExitCode(0);

        $this->assertSame('/api/v2/admin/currencies/EUR', $this->sylius->channelsStore['acme']['baseCurrency']);
        $this->assertArrayHasKey('COMMERCE_CHANNEL', $this->envFiles[Tenant::query()->firstWhere('slug', 'acme')->api_host]);
    }

    public function testDeletingTheTenantDisablesItsChannel(): void
    {
        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--users' => 0])->assertExitCode(0);

        $this->artisan('ulams:tenant:delete', ['slug' => 'acme', '--force' => true])->assertExitCode(0);

        $this->assertFalse($this->sylius->channelsStore['acme']['enabled']);
    }
}
