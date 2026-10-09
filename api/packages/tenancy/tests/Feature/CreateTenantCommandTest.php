<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Support\Facades\File;
use Mockery;
use Mockery\MockInterface;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\BucketProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DatabaseProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Tests\Mocks\FakeTenantCommandRunner;
use Ulams\Tenancy\Tests\TestCase;

class CreateTenantCommandTest extends TestCase
{
    private string $storage;
    private FakeTenantCommandRunner $runner;
    private MockInterface $database;
    private MockInterface $buckets;
    private MockInterface $domains;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = sys_get_temp_dir() . '/ulams-tenancy-test-' . uniqid();
        $this->runner = new FakeTenantCommandRunner($this->storage);
        $this->database = Mockery::mock(DatabaseProvisionerContract::class);
        $this->buckets = Mockery::mock(BucketProvisionerContract::class);
        $this->domains = Mockery::mock(DomainRegistryContract::class);
        $this->domains->shouldReceive('storagePath')->andReturn($this->storage);

        $this->app->instance(TenantCommandRunnerContract::class, $this->runner);
        $this->app->instance(DatabaseProvisionerContract::class, $this->database);
        $this->app->instance(BucketProvisionerContract::class, $this->buckets);
        $this->app->instance(DomainRegistryContract::class, $this->domains);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    public function testProvisionsEveryStepInOrder(): void
    {
        $this->database->shouldReceive('ensure')->once()
            ->withArgs(fn ($db, $user, $password) => $db === 'ulams_acme' && $user === 'ulams_acme' && strlen($password) === 40);
        $this->buckets->shouldReceive('ensure')->once()->with('ulams-acme');
        $this->domains->shouldReceive('add')->once()->withArgs(function (string $host, array $values) {
            return $host === 'acme.localhost'
                && $values['APP_NAME'] === 'Acme Academy'
                && $values['APP_URL'] === 'http://acme.localhost'
                && $values['TENANT_SLUG'] === 'acme'
                && $values['DB_DATABASE'] === 'ulams_acme'
                && $values['AWS_BUCKET'] === 'ulams-acme'
                && $values['REDIS_PREFIX'] === 'ulams_acme_'
                && str_starts_with($values['CACHE_PREFIX'], 'ulams_acme_')
                && str_starts_with($values['HORIZON_PREFIX'], 'ulams_acme_')
                && $values['FILESYSTEM_DRIVER'] === 's3'
                && $values['INITIAL_USER_EMAIL'] === 'admin@acme.ulams.app'
                && str_starts_with($values['APP_KEY'], 'base64:');
        });

        $this->artisan('ulams:tenant:create', [
            'slug' => 'acme',
            '--name' => 'Acme Academy',
            '--theme' => 'coffee',
            '--accent' => '#C2552D',
            '--users' => 3,
        ])->assertExitCode(0);

        $this->assertSame(
            ['migrate', 'passport:keys', 'passport:client', 'db:seed', 'ulams:lti:rotate-keys', 'ulams:tenant:seed-demo'],
            $this->runner->commands()
        );
        foreach ($this->runner->calls as $call) {
            $this->assertSame('acme.localhost', $call['host']);
        }
        $demo = end($this->runner->calls)['arguments'];
        $this->assertContains('--users=3', $demo);
        $this->assertContains('--theme=coffee', $demo);
        $this->assertContains('--accent=#C2552D', $demo);
        $this->assertContains('--front-url=http://acme.app.localhost', $demo);

        $tenant = Tenant::query()->firstWhere('slug', 'acme');
        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->status);
        $this->assertSame('acme.app.localhost', $tenant->front_host);
        $this->assertSame('acme.admin.localhost', $tenant->admin_host);
        $this->assertSame(
            ['database', 'bucket', 'env', 'migrate', 'passport_keys', 'passport_client', 'permissions', 'lti_keys', 'demo'],
            array_keys($tenant->steps)
        );
        $this->assertSame('PRIVATE-acme.localhost', $tenant->passport_private_key);
        $this->assertSame('PUBLIC-acme.localhost', $tenant->passport_public_key);
    }

    public function testRerunSkipsFinishedSteps(): void
    {
        $this->database->shouldReceive('ensure')->once();
        $this->buckets->shouldReceive('ensure')->once();
        $this->domains->shouldReceive('add')->once();

        $this->artisan('ulams:tenant:create', ['slug' => 'acme'])->assertExitCode(0);
        $this->runner->calls = [];

        $this->artisan('ulams:tenant:create', ['slug' => 'acme'])
            ->expectsOutputToContain('skip  database (already done)')
            ->assertExitCode(0);

        $this->assertSame([], $this->runner->commands());
    }

    public function testFailedStepIsRecordedAndTheNextRunResumesFromIt(): void
    {
        $this->database->shouldReceive('ensure')->once();
        $this->buckets->shouldReceive('ensure')->once();
        $this->domains->shouldReceive('add')->once();
        $this->runner->failOn['migrate'] = true;

        $this->artisan('ulams:tenant:create', ['slug' => 'acme'])->assertExitCode(1);

        $tenant = Tenant::query()->firstWhere('slug', 'acme');
        $this->assertSame(Tenant::STATUS_FAILED, $tenant->status);
        $this->assertStringContainsString('[migrate] migrate exploded', $tenant->last_error);
        $this->assertSame(['database', 'bucket', 'env'], array_keys($tenant->steps));

        $this->runner->failOn = [];
        $this->runner->calls = [];
        $this->artisan('ulams:tenant:create', ['slug' => 'acme'])->assertExitCode(0);

        $this->assertSame(
            ['migrate', 'passport:keys', 'passport:client', 'db:seed', 'ulams:lti:rotate-keys', 'ulams:tenant:seed-demo'],
            $this->runner->commands()
        );
        $tenant->refresh();
        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->status);
        $this->assertNull($tenant->last_error);
    }

    public function testChangingTheThemeRewritesEnvAndDemoOnly(): void
    {
        $this->database->shouldReceive('ensure')->once();
        $this->buckets->shouldReceive('ensure')->once();
        $this->domains->shouldReceive('add')->twice();

        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--theme' => 'coffee'])->assertExitCode(0);
        $this->runner->calls = [];

        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--theme' => 'nightsky'])->assertExitCode(0);

        $this->assertSame(['ulams:tenant:seed-demo'], $this->runner->commands());
        $this->assertSame('nightsky', Tenant::query()->firstWhere('slug', 'acme')->theme);
    }

    public function testDemoModeIsWrittenToTheEnvFileAndCanBeToggled(): void
    {
        $this->database->shouldReceive('ensure')->once();
        $this->buckets->shouldReceive('ensure')->once();
        $written = [];
        $this->domains->shouldReceive('add')->twice()->andReturnUsing(function (string $host, array $values) use (&$written) {
            $written[] = [$values['DEMO_MODE'], $values['ADMIN_URL']];
        });

        $this->artisan('ulams:tenant:create', ['slug' => 'acme'])->assertExitCode(0);
        $this->runner->calls = [];
        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--demo' => 'on'])->assertExitCode(0);

        $this->assertSame([['false', 'http://acme.admin.localhost'], ['true', 'http://acme.admin.localhost']], $written);
        // only the env file changes
        $this->assertSame([], $this->runner->commands());
        $this->assertTrue(Tenant::query()->firstWhere('slug', 'acme')->demo);
    }

    public function testRedoRerunsTheGivenStep(): void
    {
        $this->database->shouldReceive('ensure')->once();
        $this->buckets->shouldReceive('ensure')->once();
        $this->domains->shouldReceive('add')->once();

        $this->artisan('ulams:tenant:create', ['slug' => 'acme'])->assertExitCode(0);
        $this->runner->calls = [];

        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--redo' => ['migrate']])->assertExitCode(0);

        $this->assertSame(['migrate'], $this->runner->commands());
    }

    public function testStoredPassportKeysAreReusedInsteadOfRegenerated(): void
    {
        $this->database->shouldReceive('ensure')->once();
        $this->buckets->shouldReceive('ensure')->once();
        $this->domains->shouldReceive('add')->once();
        $this->artisan('ulams:tenant:create', ['slug' => 'acme'])->assertExitCode(0);

        File::deleteDirectory($this->storage);
        $this->runner->calls = [];
        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--redo' => ['passport_keys']])->assertExitCode(0);

        $this->assertSame([], $this->runner->commands());
        $this->assertSame('PRIVATE-acme.localhost', file_get_contents($this->storage . '/oauth-private.key'));
    }

    public function testRejectsInvalidInput(): void
    {
        $this->artisan('ulams:tenant:create', ['slug' => 'Bad_Slug'])->assertExitCode(1);
        $this->artisan('ulams:tenant:create', ['slug' => 'admin'])->assertExitCode(1);
        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--accent' => 'red'])->assertExitCode(1);
        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--redo' => ['nope']])->assertExitCode(1);
        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--demo' => 'maybe'])->assertExitCode(1);

        $this->assertSame(0, Tenant::query()->where('slug', 'acme')->count());
    }

    public function testRefusesToRunInsideATenant(): void
    {
        config(['ulams_tenancy.tenant_slug' => 'coffee']);

        $this->artisan('ulams:tenant:create', ['slug' => 'acme'])->assertExitCode(1);
    }

    public function testSyncEnvRebuildsEnvFileAndKeysOfProvisionedTenants(): void
    {
        $this->database->shouldReceive('ensure')->once();
        $this->buckets->shouldReceive('ensure')->once();
        $this->domains->shouldReceive('add')->once();
        $this->artisan('ulams:tenant:create', ['slug' => 'acme'])->assertExitCode(0);
        File::deleteDirectory($this->storage);
        $this->runner->calls = [];

        $this->domains->shouldReceive('add')->once()->withArgs(fn ($host, $values) => $host === 'acme.localhost' && $values['DB_DATABASE'] === 'ulams_acme');
        $this->artisan('ulams:tenant:sync-env', ['--migrate' => true])->assertExitCode(0);

        $this->assertSame(['migrate'], $this->runner->commands());
        $this->assertSame('PUBLIC-acme.localhost', file_get_contents($this->storage . '/oauth-public.key'));
    }

    public function testDeleteRequiresForceAndRemovesEverything(): void
    {
        $this->database->shouldReceive('ensure')->once();
        $this->buckets->shouldReceive('ensure')->once();
        $this->domains->shouldReceive('add')->once();
        $this->artisan('ulams:tenant:create', ['slug' => 'acme'])->assertExitCode(0);

        $this->artisan('ulams:tenant:delete', ['slug' => 'acme'])->assertExitCode(1);
        $this->assertSame(1, Tenant::query()->where('slug', 'acme')->count());

        $this->database->shouldReceive('drop')->once()->with('ulams_acme', 'ulams_acme');
        $this->buckets->shouldReceive('delete')->once()->with('ulams-acme');
        $this->domains->shouldReceive('remove')->once()->with('acme.localhost');
        config(['database.redis.client' => 'phpredis']); // skip the Redis purge in this test

        $this->artisan('ulams:tenant:delete', ['slug' => 'acme', '--force' => true])->assertExitCode(0);
        $this->assertSame(0, Tenant::query()->where('slug', 'acme')->count());
    }

    public function testListShowsTenants(): void
    {
        Tenant::query()->create(\Ulams\Tenancy\Support\TenantNaming::newTenantAttributes('listed'));

        $this->artisan('ulams:tenant:list')->expectsOutputToContain('listed.localhost')->assertExitCode(0);
    }
}
