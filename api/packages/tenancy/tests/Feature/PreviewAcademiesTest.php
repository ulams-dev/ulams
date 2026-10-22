<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Mockery;
use Spatie\Permission\Models\Permission;
use Ulams\Auth\Enums\AuthPermissionsEnum;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Tenancy\Jobs\DeleteTenantJob;
use Ulams\Tenancy\Jobs\ProvisionTenantJob;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Models\TenantOperation;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Services\TenantLifecycle;
use Ulams\Tenancy\Support\TenantNaming;
use Ulams\Tenancy\Tests\TestCase;

/** Preview academies (plan 8.3): throwaway tenants with a time limit, a cap per repository, a reaper and a one-day token. */
class PreviewAcademiesTest extends TestCase
{
    use CreatesUsers;

    /** @var array<int,array{host:string,arguments:list<string>}> */
    private array $calls = [];

    private string $runnerOutput = '';

    protected function setUp(): void
    {
        parent::setUp();
        config(['ulams_tenancy.platform_api' => true]);
        $self = $this;
        $this->app->instance(TenantCommandRunnerContract::class, new class ($self) implements TenantCommandRunnerContract {
            public function __construct(private PreviewAcademiesTest $test)
            {
            }

            public function run(string $host, array $arguments): string
            {
                return $this->test->record($host, $arguments);
            }
        });
        $this->app->forgetInstance(TenantLifecycle::class);
    }

    /** @param list<string> $arguments */
    public function record(string $host, array $arguments): string
    {
        $this->calls[] = ['host' => $host, 'arguments' => $arguments];

        return $this->runnerOutput;
    }

    private function platformAdmin(): User
    {
        Permission::findOrCreate(AuthPermissionsEnum::PLATFORM_ADMIN, 'api');
        $admin = $this->makeAdmin();
        $admin->givePermissionTo(AuthPermissionsEnum::PLATFORM_ADMIN);

        return $admin;
    }

    private function seedTenant(string $slug, array $extra = []): Tenant
    {
        return Tenant::query()->create(array_merge(TenantNaming::newTenantAttributes($slug), ['status' => Tenant::STATUS_ACTIVE], $extra));
    }

    private function preview(string $slug, string $group = 'acme-course', int $days = 7): Tenant
    {
        return $this->seedTenant($slug, ["expires_at" => now()->addDays($days), 'preview_group' => $group]);
    }

    public function testCreatingWithATtlMakesAThrowawayTenantInAGroup(): void
    {
        Bus::fake([ProvisionTenantJob::class]);
        $admin = $this->platformAdmin();

        $res = $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', ['slug' => 'prtwo', 'demo' => true, 'ttl_days' => 7, 'group' => 'acme-course'])->assertStatus(202);

        $tenant = Tenant::query()->where('slug', 'prtwo')->firstOrFail();
        $this->assertSame('acme-course', $tenant->preview_group);
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $tenant->expires_at->timestamp, 5);
        $this->assertTrue($tenant->isPreview());
        $res->assertJsonPath('data.tenant.preview_group', 'acme-course');
        $this->assertNotNull($res->json('data.tenant.expires_at'));
        Bus::assertDispatched(ProvisionTenantJob::class);
    }

    public function testATtlNeedsAGroupAndSaneValues(): void
    {
        Bus::fake([ProvisionTenantJob::class]);
        $admin = $this->platformAdmin();
        foreach ([['ttl_days' => 7], ['ttl_days' => 0, 'group' => 'a'], ['ttl_days' => 31, 'group' => 'a'], ['ttl_days' => 7, 'group' => 'Bad Group!']] as $extra) {
            $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', ['slug' => 'prone'] + $extra)->assertStatus(422);
        }
        $this->assertSame(0, Tenant::query()->count());
        // an ordinary tenant has no expiry
        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', ['slug' => 'prone'])->assertStatus(202);
        $this->assertNull(Tenant::query()->where('slug', 'prone')->firstOrFail()->expires_at);
    }

    public function testAGroupMayHaveAtMostFiveLivePreviews(): void
    {
        Bus::fake([ProvisionTenantJob::class]);
        $admin = $this->platformAdmin();
        foreach (range(1, 4) as $i) {
            $this->preview("live{$i}");
        }
        $this->preview('gone', days: -1); // expired: not counted
        $this->seedTenant('plain');            // not a preview: not counted
        $this->preview('other', 'other-course');

        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', ['slug' => 'live5', 'ttl_days' => 7, 'group' => 'acme-course'])->assertStatus(202);
        $refused = $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', ['slug' => 'live6', 'ttl_days' => 7, 'group' => 'acme-course'])->assertStatus(409);
        $refused->assertJsonPath('code', 'preview_limit');
        $this->assertNull(Tenant::query()->where('slug', 'live6')->first());
        // another repository is not affected, and the cap is configurable
        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', ['slug' => 'live7', 'ttl_days' => 7, 'group' => 'third-course'])->assertStatus(202);
        config(['ulams_tenancy.preview_cap' => 1]);
        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', ['slug' => 'live8', 'ttl_days' => 7, 'group' => 'other-course'])->assertStatus(409);
    }

    public function testAPreviewHandsOutAOneDayAdministratorTokenFromInsideItsTenant(): void
    {
        $tenant = $this->preview('prthree');
        $this->runnerOutput = "notice\n" . json_encode(['token' => 'ulams_pat_secret', 'expires_at' => '2026-10-23T10:00:00+00:00', 'scopes' => ['builder:read', 'builder:write']]);

        $res = $this->actingAs($this->platformAdmin(), 'api')->postJson('/api/platform/tenants/prthree/token')->assertOk();

        $res->assertJsonPath('data.token', 'ulams_pat_secret')->assertJsonPath('data.scopes', ['builder:read', 'builder:write'])->assertJsonPath('data.urls.api', $tenant->apiUrl());
        $this->assertSame([['host' => $tenant->api_host, 'arguments' => ['ulams:tenant:preview-token', '--no-interaction']]], $this->calls);
        // the token is never part of the tenant's own representation
        $this->assertStringNotContainsString('ulams_pat_secret', $this->actingAs($this->platformAdmin(), 'api')->getJson('/api/platform/tenants/prthree')->getContent());
    }

    public function testOnlyActivePreviewsHandOutATokenAndOnlyToPlatformAdministrators(): void
    {
        $admin = $this->platformAdmin();
        $this->seedTenant('realone');
        $this->preview('expired', days: -1);
        $this->seedTenant('half', ['status' => Tenant::STATUS_PROVISIONING, 'expires_at' => now()->addDay(), 'preview_group' => 'g']);
        $this->runnerOutput = json_encode(['token' => 'ulams_pat_x']);

        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants/realone/token')->assertStatus(409);
        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants/expired/token')->assertStatus(409);
        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants/half/token')->assertStatus(409);
        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants/nope/token')->assertNotFound();
        $this->assertSame([], $this->calls, 'no process ran for a tenant that may not have a token');

        $this->preview('prfour');
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/platform/tenants/prfour/token')->assertUnauthorized();
        $this->actingAs($this->makeStudent(), 'api')->postJson('/api/platform/tenants/prfour/token')->assertForbidden();
        $this->actingAs($this->makeInstructor(), 'api')->postJson('/api/platform/tenants/prfour/token')->assertForbidden();
        $this->assertSame([], $this->calls);
    }

    public function testAScopedTokenNeedsPlatformWriteToMintAPreviewToken(): void
    {
        $this->preview('prseven');
        $this->runnerOutput = json_encode(['token' => 'ulams_pat_x']);
        $admin = $this->platformAdmin();
        $mint = fn (array $scopes) => ['Authorization' => 'Bearer ' . app(PersonalAccessTokenServiceContract::class)->issue($admin, 'platform test', $scopes, 30, 'agent', 'tester', 'admin', null)->secret, 'Accept' => 'application/json'];

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/platform/tenants/prseven/token', [], $mint(['platform:read']))->assertForbidden();
        $this->assertSame([], $this->calls);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/platform/tenants/prseven/token', [], $mint(['platform:write']))->assertOk();
    }

    public function testATokenThatCannotBeMintedIsAnErrorWithoutDetails(): void
    {
        $this->preview('prfive');
        $this->runnerOutput = 'Fatal: the administrator does not exist';

        $res = $this->actingAs($this->platformAdmin(), 'api')->postJson('/api/platform/tenants/prfive/token')->assertStatus(500);

        $this->assertStringNotContainsString('administrator does not exist', $res->getContent());
    }

    public function testTheTokenRouteIs404WhenThePlatformApiIsOffOrOnATenantHost(): void
    {
        $this->preview('prsix');
        $admin = $this->platformAdmin();
        config(['ulams_tenancy.platform_api' => false]);
        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants/prsix/token')->assertNotFound();
        config(['ulams_tenancy.platform_api' => true, 'ulams_tenancy.tenant_slug' => 'coffee']);
        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants/prsix/token')->assertNotFound();
        $this->assertSame([], $this->calls);
    }

    public function testTheTokenCommandRefusesToRunOnThePlatform(): void
    {
        $this->artisan('ulams:tenant:preview-token')->expectsOutputToContain('inside a tenant')->assertFailed();
    }

    public function testTheReaperQueuesTheDeletionOfExpiredPreviewsOnly(): void
    {
        Bus::fake([DeleteTenantJob::class]);
        $this->preview('stale', days: -2);
        $this->preview('fresh', days: 3);
        $this->seedTenant('plain');
        $this->preview('busy', days: -1);
        TenantOperation::start(TenantOperation::DELETE, 'busy', TenantLifecycle::DELETE_STEPS, null);

        $this->artisan('ulams:tenant:reap', ['--dry-run' => true])->expectsOutputToContain('stale')->assertSuccessful();
        Bus::assertNotDispatched(DeleteTenantJob::class);

        $this->artisan('ulams:tenant:reap')->expectsOutputToContain('2 expired tenants')->assertSuccessful();

        Bus::assertDispatchedTimes(DeleteTenantJob::class, 1);
        $operation = TenantOperation::query()->where('tenant_slug', 'stale')->firstOrFail();
        $this->assertSame(TenantOperation::DELETE, $operation->kind);
        Bus::assertDispatched(DeleteTenantJob::class, fn ($job) => $job->operationId === $operation->id);
        $this->assertNull(TenantOperation::query()->where('tenant_slug', 'fresh')->first());
        $this->assertNull(TenantOperation::query()->where('tenant_slug', 'plain')->first());
    }

    public function testTheReaperIsScheduledHourlyOnThePlatformOnly(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'ulams:tenant:reap'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
        config(['ulams_tenancy.tenant_slug' => 'coffee']);
        $this->assertFalse($event->filtersPass(app()));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        File::deleteDirectory(sys_get_temp_dir() . '/ulams-preview-test');
        parent::tearDown();
    }
}
