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

    public function testDbPasswordOptionIsUsedForTheDatabaseStep(): void
    {
        $this->database->shouldReceive('ensure')->once()
            ->withArgs(fn ($db, $user, $password) => $password === 'a-password-of-16-chars');
        $this->buckets->shouldReceive('ensure')->once();
        $this->domains->shouldReceive('add')->once()
            ->withArgs(fn (string $host, array $values) => $values['DB_PASSWORD'] === 'a-password-of-16-chars');

        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--db-password' => 'a-password-of-16-chars'])->assertExitCode(0);

        $this->assertSame('a-password-of-16-chars', Tenant::query()->firstWhere('slug', 'acme')->db_password);
    }

    public function testShortDbPasswordIsRejected(): void
    {
        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--db-password' => 'short'])
            ->expectsOutputToContain('at least 16 characters')
            ->assertExitCode(1);

        $this->assertNull(Tenant::query()->firstWhere('slug', 'acme'));
    }

    public function testDbPasswordDoesNotChangeADatabaseThatIsAlreadySetUp(): void
    {
        $this->database->shouldReceive('ensure')->once();
        $this->buckets->shouldReceive('ensure')->once();
        $this->domains->shouldReceive('add')->once();
        $this->artisan('ulams:tenant:create', ['slug' => 'acme'])->assertExitCode(0);
        $before = Tenant::query()->firstWhere('slug', 'acme')->db_password;

        $this->artisan('ulams:tenant:create', ['slug' => 'acme', '--db-password' => 'another-password-16-chars'])->assertExitCode(0);

        $this->assertSame($before, Tenant::query()->firstWhere('slug', 'acme')->db_password);
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

    public function testTheNewDemoPresetsAreAcceptedAsThemes(): void
    {
        $this->database->shouldReceive('ensure')->times(3);
        $this->buckets->shouldReceive('ensure')->times(3);
        $this->domains->shouldReceive('add')->times(3);

        foreach (['gravity' => '#3DD6F5', 'poland' => '#C8102E', 'ulam' => '#1D3B8F'] as $slug => $accent) {
            $this->artisan('ulams:tenant:create', ['slug' => $slug, '--theme' => $slug, '--accent' => $accent])->assertExitCode(0);
            $tenant = Tenant::query()->firstWhere('slug', $slug);
            $this->assertSame($slug, $tenant->theme);
            $this->assertSame($accent, $tenant->accent);
        }
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

        config(['ulams_tenancy.scheme' => 'https', 'ulams_tenancy.content_host' => '{slug}.content.ulams.app']);
        // sync-env writes the content origin of the current naming patterns to existing tenants
        $this->domains->shouldReceive('add')->once()->withArgs(fn ($host, $values) => $host === 'acme.localhost'
            && $values['DB_DATABASE'] === 'ulams_acme'
            && $values['CONTENT_ORIGIN'] === 'https://acme.content.ulams.app');
        $this->artisan('ulams:tenant:sync-env', ['--migrate' => true])->assertExitCode(0);

        $this->assertSame(['migrate'], $this->runner->commands());
        $this->assertSame('PUBLIC-acme.localhost', file_get_contents($this->storage . '/oauth-public.key'));
    }

    public function testSyncEnvKeepsPlatformAiSettingsAndTenantOverridesWin(): void
    {
        config(['ulams_tenancy.inherited_env' => [
            'ANTHROPIC_API_KEY' => 'sk-platform-secret',
            'AI_DRIVER' => 'anthropic',
            'AI_MODEL_DEFAULT' => 'model-from-platform',
            'AI_MODEL_LIGHT' => null,
        ]]);
        $this->database->shouldReceive('ensure')->twice();
        $this->buckets->shouldReceive('ensure')->twice();
        $this->domains->shouldReceive('add')->twice();
        $this->artisan('ulams:tenant:create', ['slug' => 'acme'])->assertExitCode(0);
        $this->artisan('ulams:tenant:create', ['slug' => 'bravo'])->assertExitCode(0);
        Tenant::query()->where('slug', 'bravo')->firstOrFail()->update(['env_overrides' => ['AI_DRIVER' => 'fake', 'NOT_ALLOWED' => 'x']]);

        $seen = [];
        $this->domains->shouldReceive('add')->twice()->andReturnUsing(function ($host, $values) use (&$seen) {
            $seen[$host] = $values;
        });
        $this->artisan('ulams:tenant:sync-env')
            ->doesntExpectOutputToContain('sk-platform-secret')
            ->assertExitCode(0);

        $this->assertSame('sk-platform-secret', $seen['acme.localhost']['ANTHROPIC_API_KEY']);
        $this->assertSame('anthropic', $seen['acme.localhost']['AI_DRIVER']);
        $this->assertArrayNotHasKey('AI_MODEL_LIGHT', $seen['acme.localhost']);
        $this->assertSame('fake', $seen['bravo.localhost']['AI_DRIVER']);
        $this->assertSame('sk-platform-secret', $seen['bravo.localhost']['ANTHROPIC_API_KEY']);
        $this->assertArrayNotHasKey('NOT_ALLOWED', $seen['bravo.localhost']);
    }

    public function testSetEnvStoresOverridesEncryptedAndNeverPrintsThem(): void
    {
        Tenant::query()->create(array_merge(\Ulams\Tenancy\Support\TenantNaming::newTenantAttributes('keyed'), ['steps' => []]));

        $this->artisan('ulams:tenant:set-env', ['slug' => 'keyed', '--set' => ['ANTHROPIC_API_KEY=sk-tenant-secret']])
            ->doesntExpectOutputToContain('sk-tenant-secret')
            ->expectsOutputToContain('ANTHROPIC_API_KEY')
            ->assertExitCode(0);

        $raw = \Illuminate\Support\Facades\DB::table('tenants')->where('slug', 'keyed')->value('env_overrides');
        $this->assertStringNotContainsString('sk-tenant-secret', $raw);
        $this->assertSame('sk-tenant-secret', Tenant::query()->firstWhere('slug', 'keyed')->env_overrides['ANTHROPIC_API_KEY']);

        $this->artisan('ulams:tenant:set-env', ['slug' => 'keyed', '--set' => ['DB_PASSWORD=x']])->assertExitCode(1);
        $this->artisan('ulams:tenant:set-env', ['slug' => 'keyed', '--unset' => ['ANTHROPIC_API_KEY']])->assertExitCode(0);
        $this->assertNull(Tenant::query()->firstWhere('slug', 'keyed')->env_overrides);
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
        // the Redis purge is RedisKeyPurgerTest's job
        $redis = Mockery::mock(\Ulams\Tenancy\Support\RedisKeyPurger::class);
        $redis->shouldReceive('purge')->once()->with('ulams_acme_')->andReturn(0);
        $this->app->instance(\Ulams\Tenancy\Support\RedisKeyPurger::class, $redis);

        $this->artisan('ulams:tenant:delete', ['slug' => 'acme', '--force' => true])->assertExitCode(0);
        $this->assertSame(0, Tenant::query()->where('slug', 'acme')->count());
    }

    public function testListShowsTenants(): void
    {
        Tenant::query()->create(\Ulams\Tenancy\Support\TenantNaming::newTenantAttributes('listed'));

        $this->artisan('ulams:tenant:list')->expectsOutputToContain('listed.localhost')->assertExitCode(0);
    }
}
