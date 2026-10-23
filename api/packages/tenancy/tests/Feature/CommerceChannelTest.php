<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Support\Facades\File;
use Mockery;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\BucketProvisionerContract;
use Ulams\Tenancy\Services\Contracts\CommerceChannelProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DatabaseProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Services\TenantProvisioner;
use Ulams\Tenancy\Tests\Mocks\FakeCommerceChannelProvisioner;
use Ulams\Tenancy\Tests\Mocks\FakeTenantCommandRunner;
use Ulams\Tenancy\Tests\TestCase;
use Ulams\Tenancy\Upgrade\UpgradeSteps;

/** A tenant gets its commerce channel at provisioning, and from the upgrade step when commerce is switched on later (ADR 0131). */
class CommerceChannelTest extends TestCase
{
    private string $storage;
    private FakeCommerceChannelProvisioner $commerce;
    /** @var array<string, array<string, string>> host => env values of the last write */
    private array $envFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = sys_get_temp_dir() . '/ulams-tenancy-commerce-' . uniqid();
        $this->commerce = new FakeCommerceChannelProvisioner();
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
        $this->app->instance(CommerceChannelProvisionerContract::class, $this->commerce);
        $this->app->forgetInstance(TenantProvisioner::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    private function create(string $slug): Tenant
    {
        $this->artisan('ulams:tenant:create', ['slug' => $slug, '--users' => 0])->assertExitCode(0);

        return Tenant::query()->firstWhere('slug', $slug);
    }

    public function testTheChannelIsMadeAfterThePermissionsAndItsCodeAndSecretReachTheTenantEnv(): void
    {
        $this->assertSame(['permissions', 'commerce_channel', 'lti_keys'], array_slice(TenantProvisioner::STEPS, array_search('permissions', TenantProvisioner::STEPS), 3));

        $tenant = $this->create('acme');

        $this->assertSame('acme', $tenant->commerce_channel);
        $this->assertArrayHasKey('commerce_channel', $tenant->steps);
        $this->assertStringStartsWith('whsec_', $tenant->commerce_webhook_secret);
        $this->assertGreaterThanOrEqual(70, strlen($tenant->commerce_webhook_secret));
        $env = $this->envFiles['acme.localhost'];
        $this->assertSame(['acme', $tenant->commerce_webhook_secret], [$env['COMMERCE_CHANNEL'], $env['COMMERCE_WEBHOOK_SECRET']]);
        $this->assertSame('http://acme.localhost/api/commerce/webhooks/sylius', $this->commerce->channels['acme']['url']);
        $this->assertSame($tenant->commerce_webhook_secret, $this->commerce->channels['acme']['secret']);
    }

    public function testEveryTenantGetsItsOwnSecret(): void
    {
        $a = $this->create('acme');
        $b = $this->create('beta');

        $this->assertNotSame($a->commerce_webhook_secret, $b->commerce_webhook_secret);
        $this->assertSame('beta', $this->envFiles['beta.localhost']['COMMERCE_CHANNEL']);
        $this->assertNotSame($this->envFiles['acme.localhost']['COMMERCE_WEBHOOK_SECRET'], $this->envFiles['beta.localhost']['COMMERCE_WEBHOOK_SECRET']);
    }

    public function testWithoutAProviderTheStepIsSkippedAndNotRecordedSoItCanRunLater(): void
    {
        $this->commerce->on = false;
        $tenant = $this->create('acme');

        $this->assertNull($tenant->commerce_channel);
        $this->assertArrayNotHasKey('commerce_channel', $tenant->steps ?? []);
        $this->assertArrayNotHasKey('COMMERCE_CHANNEL', $this->envFiles['acme.localhost']);
        $this->artisan('ulams:tenant:commerce-channel')->expectsOutputToContain('nothing to do')->assertExitCode(0);
        $this->assertSame([], $this->commerce->channels);

        // commerce is switched on later: the upgrade command gives the existing tenant its channel
        $this->commerce->on = true;
        $this->artisan('ulams:tenant:commerce-channel')->assertExitCode(0);

        $tenant->refresh();
        $this->assertSame('acme', $tenant->commerce_channel);
        $this->assertArrayHasKey('commerce_channel', $tenant->steps);
        $this->assertSame('acme', $this->envFiles['acme.localhost']['COMMERCE_CHANNEL']);
    }

    public function testRunningItAgainKeepsTheSecretAndAsksTheProviderToRepairTheChannel(): void
    {
        $tenant = $this->create('acme');
        $secret = $tenant->commerce_webhook_secret;

        $this->artisan('ulams:tenant:commerce-channel', ['slug' => 'acme'])->assertExitCode(0);

        $this->assertSame($secret, $tenant->refresh()->commerce_webhook_secret);
        $this->assertSame(2, $this->commerce->channels['acme']['calls']);
        $this->assertSame($secret, $this->envFiles['acme.localhost']['COMMERCE_WEBHOOK_SECRET']);
    }

    public function testAnUnknownTenantIsAnError(): void
    {
        $this->artisan('ulams:tenant:commerce-channel', ['slug' => 'nope'])->assertExitCode(1);
    }

    public function testAProviderFailureMarksTheTenantFailedAndTheNextRunResumesAtTheChannel(): void
    {
        $this->commerce->fail = 'Sylius is down';
        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--users' => 0])->assertExitCode(1);
        $tenant = Tenant::query()->firstWhere('slug', 'acme');
        $this->assertSame(Tenant::STATUS_FAILED, $tenant->status);
        $this->assertStringContainsString('[commerce_channel] Sylius is down', (string) $tenant->last_error);
        $this->assertArrayHasKey('permissions', $tenant->steps);
        $this->assertArrayNotHasKey('commerce_channel', $tenant->steps);
        $this->assertArrayNotHasKey('lti_keys', $tenant->steps);

        $this->commerce->fail = null;
        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--users' => 0])->assertExitCode(0);

        $tenant->refresh();
        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->status);
        $this->assertSame('acme', $tenant->commerce_channel);
        $this->assertArrayHasKey('lti_keys', $tenant->steps);
    }

    public function testDeletingATenantTakesItsChannelOffSaleEvenWhenTheProviderIsDown(): void
    {
        $this->create('acme');
        $this->artisan('ulams:tenant:delete', ['slug' => 'acme', '--force' => true])->assertExitCode(0);
        $this->assertSame(['acme'], $this->commerce->disabled);

        $this->create('beta');
        $this->commerce->disableFails = true;
        $this->artisan('ulams:tenant:delete', ['slug' => 'beta', '--force' => true])->assertExitCode(0);
        $this->assertNull(Tenant::query()->firstWhere('slug', 'beta'));
    }

    public function testASecretIsNeverPartOfAnArrayOrJsonOfATenant(): void
    {
        $tenant = $this->create('acme');

        $this->assertArrayNotHasKey('commerce_webhook_secret', $tenant->toArray());
        $this->assertStringNotContainsString((string) $tenant->commerce_webhook_secret, $tenant->toJson());
        $this->assertNotSame($tenant->commerce_webhook_secret, \DB::table('tenants')->where('slug', 'acme')->value('commerce_webhook_secret'), 'encrypted at rest');
    }

    public function testTheUpgradeStepIsRegisteredOnThePlatformAndRunsEveryTime(): void
    {
        UpgradeSteps::flush();
        \Ulams\Tenancy\Upgrade\DefaultUpgradeSteps::register();
        $step = collect(UpgradeSteps::all())->first(fn ($s) => $s->name === 'commerce_channel');

        $this->assertNotNull($step);
        $this->assertSame([UpgradeSteps::PLATFORM, false], [$step->scope, $step->once]);
    }
}
