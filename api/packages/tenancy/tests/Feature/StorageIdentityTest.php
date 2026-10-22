<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mockery;
use Mockery\MockInterface;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\BucketProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DatabaseProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Services\Contracts\StorageIdentityProvisionerContract;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Services\NullStorageIdentityProvisioner;
use Ulams\Tenancy\Services\SeaweedFsIdentityProvisioner;
use Ulams\Tenancy\Support\TenantNaming;
use Ulams\Tenancy\Tests\Mocks\FakeStorageIdentityProvisioner;
use Ulams\Tenancy\Tests\Mocks\FakeTenantCommandRunner;
use Ulams\Tenancy\Tests\TestCase;

/**
 * Per-tenant object-store identities (ADR 0041): provisioning, the encrypted copy on the tenant row,
 * the env file, rotation, pruning and deletion, against an in-memory identity API.
 */
class StorageIdentityTest extends TestCase
{
    private string $storage;
    private FakeStorageIdentityProvisioner $identities;
    private MockInterface $buckets;
    /** @var array<string, array<string, string>> host => env values of the last write */
    private array $envFiles = [];
    /** @var list<string> */
    private array $order = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = sys_get_temp_dir() . '/ulams-tenancy-storage-' . uniqid();
        $this->identities = new FakeStorageIdentityProvisioner();
        $database = Mockery::mock(DatabaseProvisionerContract::class);
        $database->shouldReceive('ensure', 'drop');
        $this->buckets = Mockery::mock(BucketProvisionerContract::class);
        $this->buckets->shouldReceive('ensure');
        $this->buckets->shouldReceive('delete')->andReturnUsing(function (string $bucket) {
            $slug = substr($bucket, strlen('ulams-'));
            $this->order[] = 'bucket-deleted:' . $bucket . ':identity-' . (isset($this->identities->identities[$slug]) ? 'present' : 'gone');
        });
        $domains = Mockery::mock(DomainRegistryContract::class);
        $domains->shouldReceive('storagePath')->andReturn($this->storage);
        $domains->shouldReceive('add')->andReturnUsing(function (string $host, array $values) {
            $this->envFiles[$host] = $values;
        });
        $domains->shouldReceive('remove');

        $this->app->instance(TenantCommandRunnerContract::class, new FakeTenantCommandRunner($this->storage));
        $this->app->instance(DatabaseProvisionerContract::class, $database);
        $this->app->instance(BucketProvisionerContract::class, $this->buckets);
        $this->app->instance(DomainRegistryContract::class, $domains);
        $this->app->instance(StorageIdentityProvisionerContract::class, $this->identities);
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

    public function testEveryTenantGetsItsOwnKeyInItsEnvFile(): void
    {
        $acme = $this->create('acme');
        $beta = $this->create('beta');

        $this->assertNotNull($acme->storage_key);
        $this->assertNotSame($acme->storage_key, $beta->storage_key);
        $this->assertNotSame($acme->storage_secret, $beta->storage_secret);
        $this->assertSame($acme->storage_key, $this->envFiles['acme.localhost']['AWS_ACCESS_KEY_ID']);
        $this->assertSame($acme->storage_secret, $this->envFiles['acme.localhost']['AWS_SECRET_ACCESS_KEY']);
        $this->assertSame($beta->storage_key, $this->envFiles['beta.localhost']['AWS_ACCESS_KEY_ID']);
        $this->assertSame('ulams-acme', $this->identities->identities['acme']['bucket']);
        $this->assertSame('ulams-beta', $this->identities->identities['beta']['bucket']);
        $this->assertArrayHasKey('storage_identity', $acme->steps);
    }

    public function testTheKeysAreEncryptedAtRestAndHiddenFromSerialisation(): void
    {
        $tenant = $this->create('acme');
        $raw = (array) DB::table('tenants')->where('slug', 'acme')->first();

        $this->assertNotSame($tenant->storage_key, $raw['storage_key']);
        $this->assertNotSame($tenant->storage_secret, $raw['storage_secret']);
        $this->assertStringNotContainsString($tenant->storage_secret, (string) $raw['storage_secret']);
        $this->assertArrayNotHasKey('storage_secret', $tenant->toArray());
        $this->assertArrayNotHasKey('storage_key', $tenant->toArray());
        $this->assertStringNotContainsString($tenant->storage_secret, json_encode($tenant));
    }

    public function testRunningTheStepAgainKeepsTheWorkingKey(): void
    {
        $first = $this->create('acme');
        $key = $first->storage_key;

        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--redo' => ['storage_identity'], '--users' => 0])->assertExitCode(0);

        $this->assertSame($key, Tenant::query()->firstWhere('slug', 'acme')->storage_key);
        $this->assertCount(1, $this->identities->identities['acme']['keys']);
    }

    public function testSyncEnvRebuildsTheEnvFileFromTheStoredKey(): void
    {
        $tenant = $this->create('acme');
        $this->envFiles = [];

        $this->artisan('ulams:tenant:sync-env')->assertExitCode(0);

        $this->assertSame($tenant->storage_key, $this->envFiles['acme.localhost']['AWS_ACCESS_KEY_ID']);
        $this->assertSame($tenant->storage_secret, $this->envFiles['acme.localhost']['AWS_SECRET_ACCESS_KEY']);
    }

    public function testRotationIssuesANewKeyAndKeepsTheOldOneUntilPruned(): void
    {
        $tenant = $this->create('acme');
        $old = $tenant->storage_key;

        $this->artisan('ulams:tenant:sync-env', ['--rotate-storage-keys' => true])
            ->expectsOutputToContain('storage key issued')
            ->assertExitCode(0);

        $rotated = Tenant::query()->firstWhere('slug', 'acme');
        $this->assertNotSame($old, $rotated->storage_key);
        $this->assertSame($rotated->storage_key, $this->envFiles['acme.localhost']['AWS_ACCESS_KEY_ID']);
        $this->assertArrayHasKey($old, $this->identities->identities['acme']['keys'], 'the old key still works, so running processes survive');
        $this->assertCount(2, $this->identities->identities['acme']['keys']);

        $this->artisan('ulams:tenant:sync-env', ['--prune-storage-keys' => true])
            ->expectsOutputToContain('old storage keys deleted')
            ->assertExitCode(0);

        $this->assertSame([$rotated->storage_key], array_keys($this->identities->identities['acme']['keys']));
    }

    public function testRotationCanBeLimitedToOneTenant(): void
    {
        $acme = $this->create('acme');
        $beta = $this->create('beta');

        $this->artisan('ulams:tenant:sync-env', ['--rotate-storage-keys' => true, '--tenant' => ['acme']])->assertExitCode(0);

        $this->assertNotSame($acme->storage_key, Tenant::query()->firstWhere('slug', 'acme')->storage_key);
        $this->assertSame($beta->storage_key, Tenant::query()->firstWhere('slug', 'beta')->storage_key);
    }

    public function testATenantCreatedBeforeIdentitiesExistedGetsOneFromTheRotationFlag(): void
    {
        $tenant = $this->create('acme');
        $tenant->forget('storage_identity');
        $tenant->storage_key = null;
        $tenant->storage_secret = null;
        $tenant->save();
        $this->identities->identities = [];
        $this->assertArrayNotHasKey('AWS_ACCESS_KEY_ID', TenantNaming::envValues($tenant->fresh()));

        $this->artisan('ulams:tenant:sync-env', ['--rotate-storage-keys' => true])->assertExitCode(0);

        $fresh = Tenant::query()->firstWhere('slug', 'acme');
        $this->assertNotNull($fresh->storage_key);
        $this->assertSame($fresh->storage_key, $this->envFiles['acme.localhost']['AWS_ACCESS_KEY_ID']);
        $this->assertArrayHasKey('storage_identity', $fresh->steps);
    }

    public function testDeletingATenantRevokesItsIdentityBeforeTheBucketGoes(): void
    {
        $this->create('acme');
        $this->identities->log = [];

        $this->artisan('ulams:tenant:delete', ['slug' => 'acme', '--force' => true])->assertExitCode(0);

        $this->assertContains('revoke:acme', $this->identities->log);
        $this->assertSame(['bucket-deleted:ulams-acme:identity-gone'], $this->order);
        $this->assertArrayNotHasKey('acme', $this->identities->identities);
        $this->assertNull(Tenant::query()->firstWhere('slug', 'acme'));
    }

    public function testWithoutAnIdentityDriverTheTenantKeepsThePlatformKeys(): void
    {
        $this->app->instance(StorageIdentityProvisionerContract::class, new NullStorageIdentityProvisioner());

        $tenant = $this->create('acme');

        $this->assertNull($tenant->storage_key);
        $this->assertArrayNotHasKey('AWS_ACCESS_KEY_ID', $this->envFiles['acme.localhost']);
        $this->assertArrayNotHasKey('AWS_SECRET_ACCESS_KEY', $this->envFiles['acme.localhost']);
        $this->assertArrayHasKey('storage_identity', $tenant->steps);

        $this->artisan('ulams:tenant:sync-env', ['--rotate-storage-keys' => true])
            ->expectsOutputToContain('storage identities are off')
            ->assertExitCode(0);
    }

    public function testTheDriverIsChosenByConfig(): void
    {
        $this->app->forgetInstance(StorageIdentityProvisionerContract::class);
        $this->app->offsetUnset(StorageIdentityProvisionerContract::class);
        (new \Ulams\Tenancy\UlamsTenancyServiceProvider($this->app))->register();

        config(['ulams_tenancy.storage_identity.driver' => 'none']);
        $this->assertInstanceOf(NullStorageIdentityProvisioner::class, $this->app->make(StorageIdentityProvisionerContract::class));

        $this->app->forgetInstance(StorageIdentityProvisionerContract::class);
        config(['ulams_tenancy.storage_identity.driver' => 'seaweedfs', 'ulams_tenancy.s3.endpoint' => 'http://storage:8333']);
        $this->assertInstanceOf(SeaweedFsIdentityProvisioner::class, $this->app->make(StorageIdentityProvisionerContract::class));

        $this->app->forgetInstance(StorageIdentityProvisionerContract::class);
        config(['ulams_tenancy.storage_identity.driver' => 'minio']);
        $this->expectExceptionMessage('TENANCY_S3_IDENTITY_DRIVER must be seaweedfs or none');
        $this->app->make(StorageIdentityProvisionerContract::class);
    }

    public function testThePlatformApiNeverShowsTheKeys(): void
    {
        $tenant = $this->create('acme');
        $resource = json_encode((new \Ulams\Tenancy\Http\Resources\TenantResource($tenant))->toArray(request()));

        $this->assertStringNotContainsString((string) $tenant->storage_secret, $resource);
        $this->assertStringNotContainsString((string) $tenant->storage_key, $resource);
    }
}
