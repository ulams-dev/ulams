<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Mockery;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;
use Ulams\Auth\Enums\AuthPermissionsEnum;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Tenancy\Jobs\DeleteTenantJob;
use Ulams\Tenancy\Jobs\ProvisionTenantJob;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Models\TenantOperation;
use Ulams\Tenancy\Services\Contracts\BucketProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DatabaseProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Services\TenantLifecycle;
use Ulams\Tenancy\Services\TenantProvisioner;
use Ulams\Tenancy\Support\RedisKeyPurger;
use Ulams\Tenancy\Tests\Mocks\FakeTenantCommandRunner;
use Ulams\Tenancy\Tests\TestCase;

/**
 * The platform tenant API (ADR 0078): off by default, platform hosts only, platform administrators
 * only, scoped tokens need platform:read|write, creation and deletion are queued operations.
 */
class PlatformApiTest extends TestCase
{
    use CreatesUsers;

    private string $storage;
    private FakeTenantCommandRunner $runner;
    private MockInterface $database;
    private MockInterface $buckets;
    private MockInterface $domains;
    private MockInterface $redis;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ulams_tenancy.platform_api' => true]);

        $this->storage = sys_get_temp_dir() . '/ulams-platform-api-test-' . uniqid();
        $this->runner = new FakeTenantCommandRunner($this->storage);
        $this->database = Mockery::mock(DatabaseProvisionerContract::class);
        $this->buckets = Mockery::mock(BucketProvisionerContract::class);
        $this->domains = Mockery::mock(DomainRegistryContract::class);
        $this->redis = Mockery::mock(RedisKeyPurger::class);
        $this->domains->shouldReceive('storagePath')->andReturn($this->storage);
        $this->app->instance(TenantCommandRunnerContract::class, $this->runner);
        $this->app->instance(DatabaseProvisionerContract::class, $this->database);
        $this->app->instance(BucketProvisionerContract::class, $this->buckets);
        $this->app->instance(DomainRegistryContract::class, $this->domains);
        $this->app->instance(RedisKeyPurger::class, $this->redis);
        // the provisioner and lifecycle singletons must see the mocks
        $this->app->forgetInstance(TenantProvisioner::class);
        $this->app->forgetInstance(TenantLifecycle::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    private function platformAdmin(): User
    {
        Permission::findOrCreate(AuthPermissionsEnum::PLATFORM_ADMIN, 'api');
        $admin = $this->makeAdmin();
        $admin->givePermissionTo(AuthPermissionsEnum::PLATFORM_ADMIN);

        return $admin;
    }

    private function mint(User $user, array $scopes): array
    {
        $issued = app(PersonalAccessTokenServiceContract::class)->issue($user, 'platform test', $scopes, 30, 'agent', 'tester', 'admin', null);

        return ['Authorization' => 'Bearer ' . $issued->secret, 'Accept' => 'application/json'];
    }

    private function seedTenant(string $slug = 'acme', string $status = Tenant::STATUS_ACTIVE, array $extra = []): Tenant
    {
        return Tenant::query()->create(array_merge(\Ulams\Tenancy\Support\TenantNaming::newTenantAttributes($slug), ['status' => $status], $extra));
    }

    public function testEveryRouteIs404WhenTheApiIsOff(): void
    {
        config(['ulams_tenancy.platform_api' => false]);
        $admin = $this->platformAdmin();
        foreach ([['getJson', '/api/platform/tenants'], ['postJson', '/api/platform/tenants'], ['getJson', '/api/platform/operations'], ['deleteJson', '/api/platform/tenants/acme']] as [$method, $url]) {
            $this->actingAs($admin, 'api')->{$method}($url, $method === 'getJson' ? [] : ['slug' => 'acme', 'confirm' => 'acme'])->assertNotFound();
        }
        // not even a 401: unauthenticated callers learn nothing either
        $this->getJson('/api/platform/tenants')->assertNotFound();
    }

    public function testEveryRouteIs404OnATenantHost(): void
    {
        config(['ulams_tenancy.tenant_slug' => 'coffee']);
        $admin = $this->platformAdmin();
        $this->actingAs($admin, 'api')->getJson('/api/platform/tenants')->assertNotFound();
        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', ['slug' => 'acme'])->assertNotFound();
        $this->assertSame(0, Tenant::query()->count());
    }

    public function testNeedsALoginAndThePlatformAdminPermission(): void
    {
        $this->getJson('/api/platform/tenants')->assertUnauthorized();
        $this->actingAs($this->makeStudent(), 'api')->getJson('/api/platform/tenants')->assertForbidden();
        // only the permission opens it, not a role by itself
        $this->actingAs($this->makeInstructor(), 'api')->getJson('/api/platform/tenants')->assertForbidden();
        $this->actingAs($this->makeInstructor(), 'api')->postJson('/api/platform/tenants', ['slug' => 'acme'])->assertForbidden();
        $this->assertSame(0, Tenant::query()->count());
    }

    public function testListAndShowNeverExposeSecrets(): void
    {
        $this->seedTenant('acme', Tenant::STATUS_ACTIVE, ['env_overrides' => ['AI_DRIVER' => 'sk-very-secret-value']]);
        $this->seedTenant('bravo', Tenant::STATUS_FAILED, ['last_error' => '[migrate] boom']);
        $admin = $this->platformAdmin();

        $list = $this->actingAs($admin, 'api')->getJson('/api/platform/tenants')->assertOk();
        $this->assertSame(['acme', 'bravo'], array_column($list->json('data'), 'slug'));
        $list->assertJsonPath('data.0.status', 'active')->assertJsonPath('data.0.env_override_keys', ['AI_DRIVER'])->assertJsonPath('data.1.last_error', '[migrate] boom');
        $this->assertStringNotContainsString('sk-very-secret-value', $list->getContent());
        foreach (['db_password', 'app_key', 'passport', 'redis_prefix'] as $secret) {
            $this->assertStringNotContainsString($secret, $list->getContent());
        }

        $one = $this->actingAs($admin, 'api')->getJson('/api/platform/tenants/acme')->assertOk();
        $one->assertJsonPath('data.urls.api', 'http://acme.localhost');
        $this->assertStringNotContainsString('sk-very-secret-value', $one->getContent());
        $this->actingAs($admin, 'api')->getJson('/api/platform/tenants/nope')->assertNotFound();
    }

    public function testCreateQueuesAJobAndAnOperation(): void
    {
        Bus::fake([ProvisionTenantJob::class]);
        $admin = $this->platformAdmin();

        $res = $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', ['slug' => 'acme', 'name' => 'Acme Academy', 'theme' => 'coffee', 'accent' => '#C2552D', 'users' => 3, 'demo' => true])->assertStatus(202);
        $res->assertJsonPath('data.operation.status', 'queued')->assertJsonPath('data.operation.kind', 'create')->assertJsonPath('data.tenant.status', 'provisioning');
        $this->assertSame(TenantProvisioner::STEPS, array_column($res->json('data.operation.steps'), 'name'));

        $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
        $this->assertSame(['Acme Academy', 'coffee', '#C2552D', true], [$tenant->name, $tenant->theme, $tenant->accent, $tenant->demo]);
        Bus::assertDispatched(ProvisionTenantJob::class, fn ($job) => $job->operationId === $res->json('data.operation.id') && $job->users === 3);
    }

    public function testCreateValidationConflictsAndResume(): void
    {
        Bus::fake([ProvisionTenantJob::class]);
        $admin = $this->platformAdmin();
        foreach ([['slug' => 'Bad_Slug'], ['slug' => 'admin'], ['slug' => 'acme', 'accent' => 'red'], ['slug' => 'acme', 'users' => 500], ['slug' => 'acme', 'name' => '<b>x</b>'], []] as $body) {
            $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', $body)->assertStatus(422);
        }
        $this->assertSame(0, Tenant::query()->count());

        $this->seedTenant('acme');
        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', ['slug' => 'acme'])->assertStatus(409);

        // a failed tenant is resumed by asking for it again; while that runs, a second ask is refused
        $this->seedTenant('bravo', Tenant::STATUS_FAILED);
        $first = $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', ['slug' => 'bravo'])->assertStatus(202);
        $again = $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', ['slug' => 'bravo'])->assertStatus(409);
        $again->assertJsonPath('code', 'operation_in_progress')->assertJsonPath('data.operation.id', $first->json('data.operation.id'));
    }

    public function testTheJobRunsEveryStepAndRecordsThem(): void
    {
        $this->database->shouldReceive('ensure')->once();
        $this->buckets->shouldReceive('ensure')->once();
        $this->domains->shouldReceive('add')->once();
        $tenant = app(TenantLifecycle::class)->prepare('acme', ['name' => 'Acme Academy']);
        $operation = TenantOperation::start(TenantOperation::CREATE, 'acme', TenantProvisioner::STEPS, 7, ['users' => 2]);

        (new ProvisionTenantJob($operation->id, 2))->handle(app(TenantProvisioner::class), app(DomainRegistryContract::class));

        $operation->refresh();
        $this->assertSame(TenantOperation::SUCCEEDED, $operation->status);
        $this->assertSame(array_fill(0, count(TenantProvisioner::STEPS), 'succeeded'), array_column($operation->steps, 'status'));
        $this->assertNotNull($operation->finished_at);
        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->refresh()->status);
        $this->assertSame(['migrate', 'passport:keys', 'passport:client', 'db:seed', 'ulams:lti:rotate-keys', 'ulams:tenant:seed-demo'], $this->runner->commands());

        $admin = $this->platformAdmin();
        $this->actingAs($admin, 'api')->getJson('/api/platform/operations/' . $operation->id)->assertOk()->assertJsonPath('data.status', 'succeeded')->assertJsonPath('data.tenant', 'acme');
    }

    public function testAFailedStepIsKeptOnTheOperationAndTheTenant(): void
    {
        $this->database->shouldReceive('ensure')->once();
        $this->buckets->shouldReceive('ensure')->once();
        $this->domains->shouldReceive('add')->once();
        $this->runner->failOn['migrate'] = true;
        app(TenantLifecycle::class)->prepare('acme');
        $operation = TenantOperation::start(TenantOperation::CREATE, 'acme', TenantProvisioner::STEPS, null);

        (new ProvisionTenantJob($operation->id))->handle(app(TenantProvisioner::class), app(DomainRegistryContract::class));

        $operation->refresh();
        $this->assertSame(TenantOperation::FAILED, $operation->status);
        $this->assertStringContainsString('migrate', (string) $operation->error);
        $byName = array_column($operation->steps, null, 'name');
        $this->assertSame('succeeded', $byName['env']['status']);
        $this->assertSame('failed', $byName['migrate']['status']);
        $this->assertSame('pending', $byName['demo']['status']);
        $this->assertSame(Tenant::STATUS_FAILED, Tenant::query()->firstWhere('slug', 'acme')->status);
    }

    public function testDeleteNeedsTheSlugAsConfirmation(): void
    {
        Bus::fake([DeleteTenantJob::class]);
        $this->seedTenant('acme');
        $admin = $this->platformAdmin();

        $this->actingAs($admin, 'api')->deleteJson('/api/platform/tenants/acme')->assertStatus(422);
        $this->actingAs($admin, 'api')->deleteJson('/api/platform/tenants/acme', ['confirm' => 'bravo'])->assertStatus(422);
        $this->actingAs($admin, 'api')->deleteJson('/api/platform/tenants/nope', ['confirm' => 'nope'])->assertNotFound();
        Bus::assertNotDispatched(DeleteTenantJob::class);
        $this->assertNotNull(Tenant::query()->firstWhere('slug', 'acme'));

        $res = $this->actingAs($admin, 'api')->deleteJson('/api/platform/tenants/acme', ['confirm' => 'acme'])->assertStatus(202);
        $res->assertJsonPath('data.operation.kind', 'delete')->assertJsonPath('data.operation.status', 'queued');
        $this->assertSame(TenantLifecycle::DELETE_STEPS, array_column($res->json('data.operation.steps'), 'name'));
        Bus::assertDispatched(DeleteTenantJob::class);
        $this->actingAs($admin, 'api')->deleteJson('/api/platform/tenants/acme', ['confirm' => 'acme'])->assertStatus(409);
    }

    public function testTheDeleteJobRemovesEverythingAndRecordsTheSteps(): void
    {
        $this->seedTenant('acme');
        $this->database->shouldReceive('drop')->once()->with('ulams_acme', 'ulams_acme');
        $this->buckets->shouldReceive('delete')->once()->with('ulams-acme');
        $this->domains->shouldReceive('remove')->once()->with('acme.localhost');
        $this->redis->shouldReceive('purge')->once()->with('ulams_acme_');
        $operation = TenantOperation::start(TenantOperation::DELETE, 'acme', TenantLifecycle::DELETE_STEPS, null);

        (new DeleteTenantJob($operation->id))->handle(app(TenantLifecycle::class));

        $operation->refresh();
        $this->assertSame(TenantOperation::SUCCEEDED, $operation->status);
        $this->assertSame(['succeeded', 'succeeded', 'succeeded', 'succeeded'], array_column($operation->steps, 'status'));
        $this->assertNull(Tenant::query()->firstWhere('slug', 'acme'));
    }

    public function testADeleteThatFailsKeepsTheTenantAndSaysWhere(): void
    {
        $this->seedTenant('acme');
        $this->database->shouldReceive('drop')->once();
        $this->buckets->shouldReceive('delete')->once()->andThrow(new \RuntimeException('the bucket is busy'));
        $operation = TenantOperation::start(TenantOperation::DELETE, 'acme', TenantLifecycle::DELETE_STEPS, null);

        (new DeleteTenantJob($operation->id))->handle(app(TenantLifecycle::class));

        $operation->refresh();
        $this->assertSame(TenantOperation::FAILED, $operation->status);
        $this->assertSame('the bucket is busy', $operation->error);
        $this->assertSame('failed', array_column($operation->steps, null, 'name')['bucket']['status']);
        $this->assertNotNull(Tenant::query()->firstWhere('slug', 'acme'));
    }

    public function testEnvOverridesAreLimitedToTheAllowList(): void
    {
        $this->seedTenant('acme');
        $this->domains->shouldReceive('add')->zeroOrMoreTimes();
        $admin = $this->platformAdmin();

        $res = $this->actingAs($admin, 'api')->patchJson('/api/platform/tenants/acme/env', ['set' => ['AI_DRIVER' => 'fake']])->assertOk();
        $res->assertJsonPath('data.env_override_keys', ['AI_DRIVER']);
        $this->assertStringNotContainsString('fake', $res->getContent());
        $this->assertSame(['AI_DRIVER' => 'fake'], Tenant::query()->firstWhere('slug', 'acme')->env_overrides);

        foreach ([['set' => ['DB_PASSWORD' => 'x']], ['set' => ['AI_DRIVER' => '']], ['unset' => ['APP_KEY']], []] as $body) {
            $this->actingAs($admin, 'api')->patchJson('/api/platform/tenants/acme/env', $body)->assertStatus(422);
        }
        $this->assertSame(['AI_DRIVER' => 'fake'], Tenant::query()->firstWhere('slug', 'acme')->env_overrides);

        $this->actingAs($admin, 'api')->patchJson('/api/platform/tenants/acme/env', ['unset' => ['AI_DRIVER']])->assertOk()->assertJsonPath('data.env_override_keys', []);
        $this->actingAs($admin, 'api')->patchJson('/api/platform/tenants/nope/env', ['set' => ['AI_DRIVER' => 'fake']])->assertNotFound();
    }

    public function testOperationsListAndShow(): void
    {
        $admin = $this->platformAdmin();
        $op = TenantOperation::start(TenantOperation::CREATE, 'acme', ['database'], (int) $admin->getKey());
        $this->actingAs($admin, 'api')->getJson('/api/platform/operations')->assertOk()->assertJsonPath('data.0.id', $op->id);
        $this->actingAs($admin, 'api')->getJson('/api/platform/operations/' . $op->id)->assertOk()->assertJsonPath('data.steps.0.name', 'database');
        $this->actingAs($admin, 'api')->getJson('/api/platform/operations/' . str_repeat('a', 26))->assertNotFound();
        $this->actingAs($admin, 'api')->getJson('/api/platform/operations/not-an-id')->assertNotFound();
    }

    public function testScopedTokensNeedThePlatformScope(): void
    {
        Bus::fake([ProvisionTenantJob::class]);
        $this->seedTenant('acme');
        $admin = $this->platformAdmin();
        $read = $this->mint($admin, ['platform:read']);
        $write = $this->mint($admin, ['platform:write']);
        $courses = $this->mint($admin, ['courses:write']);

        $this->getJson('/api/platform/tenants', $read)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/platform/tenants', ['slug' => 'bravo'], $read)->assertForbidden()->assertJsonPath('error', 'scope_missing');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/platform/tenants', $courses)->assertForbidden()->assertJsonPath('error', 'scope_missing');
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/platform/tenants', ['slug' => 'bravo'], $write)->assertStatus(202);
        // a token of an ordinary user with the scope still needs the permission
        $this->app['auth']->forgetGuards();
        $student = $this->mint($this->makeStudent(), ['platform:read']);
        $this->getJson('/api/platform/tenants', $student)->assertForbidden();
    }

    public function testMetaAdvertisesTheApiOnlyWhenOnOnThePlatform(): void
    {
        $this->getJson('/api/meta')->assertOk()->assertJsonPath('data.features.platformApi', true);
        config(['ulams_tenancy.platform_api' => false]);
        $this->getJson('/api/meta')->assertOk()->assertJsonPath('data.features.platformApi', false);
    }
}
