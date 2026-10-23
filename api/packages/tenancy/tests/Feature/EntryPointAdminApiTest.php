<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Ulams\Auth\Models\Group;
use Ulams\Core\Enums\UserRole;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Tenancy\Database\Seeders\TenancyPermissionSeeder;
use Ulams\Tenancy\Models\EntryPoint;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\EntryPointGatewayContract;
use Ulams\Tenancy\Services\EntryPointService;
use Ulams\Tenancy\Support\TenantNaming;
use Ulams\Tenancy\Tests\TestCase;

/**
 * The tenant admin API of entry points (ADR 0195): access control on every route, the academy always being the
 * one the process serves, and the checks only the tenant database can make. The platform table is reached
 * through the gateway, bound here to the service itself; the process boundary has its own test.
 */
class EntryPointAdminApiTest extends TestCase
{
    use CreatesUsers;

    private Tenant $coffee;
    private Tenant $tea;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([UserRole::ADMIN, UserRole::STUDENT, UserRole::TUTOR] as $role) {
            Role::findOrCreate($role, 'api');
        }
        $this->seed(TenancyPermissionSeeder::class);
        config([
            'ulams_tenancy.tenant_slug' => 'coffee',
            'ulams_tenancy.entry_platform_suffixes' => ['.app.localhost'],
            'ulams_tenancy.entry_trusted_suffixes' => ['.example.test'],
        ]);
        $this->app->instance(EntryPointGatewayContract::class, app(EntryPointService::class));
        $this->coffee = Tenant::query()->create(array_merge(TenantNaming::newTenantAttributes('coffee'), ['status' => Tenant::STATUS_ACTIVE]));
        $this->tea = Tenant::query()->create(array_merge(TenantNaming::newTenantAttributes('tea'), ['status' => Tenant::STATUS_ACTIVE]));
    }

    /** @return array<string, mixed> */
    private function body(array $extra = []): array
    {
        return array_merge(['host' => 'sales.coffee.example.test', 'key' => 'sales', 'kind' => 'academy', 'label' => 'Sales'], $extra);
    }

    private function entryOf(string $tenant, array $extra = []): array
    {
        return app(EntryPointService::class)->create($tenant, $this->body($extra), null);
    }

    public function testGuestsGet401AndEveryoneWithoutThePermissionGets403OnEveryRoute(): void
    {
        $entry = $this->entryOf('coffee');
        $routes = [
            ['GET', '/api/admin/entry-points'],
            ['POST', '/api/admin/entry-points'],
            ['GET', '/api/admin/entry-points/audit'],
            ['GET', "/api/admin/entry-points/{$entry['id']}"],
            ['PATCH', "/api/admin/entry-points/{$entry['id']}"],
            ['DELETE', "/api/admin/entry-points/{$entry['id']}"],
        ];
        foreach ($routes as [$method, $uri]) {
            $this->json($method, $uri, ['label' => 'x'])->assertUnauthorized();
        }
        foreach ([$this->makeStudent(), $this->makeInstructor()] as $user) {
            foreach ($routes as [$method, $uri]) {
                $this->actingAs($user, 'api')->json($method, $uri, ['label' => 'x'])->assertForbidden();
            }
        }
        $this->assertSame('Sales', EntryPoint::query()->findOrFail($entry['id'])->label);
        $this->assertSame(1, EntryPoint::query()->count());
    }

    public function testTheAdminRoleHasThePermissionAndAnyoneCanBeGivenIt(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')->getJson('/api/admin/entry-points')->assertOk();

        $tutor = $this->makeInstructor();
        $tutor->givePermissionTo('entry_points_manage');
        $this->actingAs($tutor, 'api')->getJson('/api/admin/entry-points')->assertOk();
    }

    public function testEveryRouteIs404OnAPlatformHost(): void
    {
        config(['ulams_tenancy.tenant_slug' => null]);
        $admin = $this->makeAdmin();
        $entry = $this->entryOf('coffee');

        foreach ([['GET', '/api/admin/entry-points'], ['POST', '/api/admin/entry-points'], ['GET', '/api/admin/entry-points/audit'], ['GET', "/api/admin/entry-points/{$entry['id']}"],
            ['PATCH', "/api/admin/entry-points/{$entry['id']}"], ['DELETE', "/api/admin/entry-points/{$entry['id']}"]] as [$method, $uri]) {
            $this->actingAs($admin, 'api')->json($method, $uri, $this->body())->assertNotFound();
        }
        $this->assertSame(1, EntryPoint::query()->count());
    }

    public function testCreateListShowUpdateDelete(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin@coffee.test']);
        $this->actingAs($admin, 'api');

        $created = $this->postJson('/api/admin/entry-points', $this->body(['theme' => ['accent' => '#C2552D']]))->assertCreated()->assertJsonPath('data.status', 'active')->assertJsonPath('data.theme.accent', '#c2552d');
        $id = $created->json('data.id');

        $this->getJson('/api/admin/entry-points')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->getJson("/api/admin/entry-points/{$id}")->assertOk()->assertJsonPath('data.host', 'sales.coffee.example.test');
        $this->patchJson("/api/admin/entry-points/{$id}", ['label' => 'Sales team', 'status' => 'disabled'])->assertOk()
            ->assertJsonPath('data.label', 'Sales team')->assertJsonPath('data.status', 'disabled');
        $this->deleteJson("/api/admin/entry-points/{$id}")->assertOk();
        $this->getJson("/api/admin/entry-points/{$id}")->assertNotFound();
        $this->getJson('/api/admin/entry-points')->assertOk()->assertJsonCount(0, 'data');

        $audit = $this->getJson('/api/admin/entry-points/audit')->assertOk()->json('data');
        $this->assertSame(['deleted', 'disabled', 'created'], array_column($audit, 'action'));
        $this->assertSame('admin@coffee.test', $audit[0]['actor_email']);
        $this->assertSame($admin->getKey(), $audit[0]['actor_id']);
    }

    public function testTheAcademyIsTheProcessAcademyNeverARequestValue(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'api');

        $this->postJson('/api/admin/entry-points', $this->body(['tenant' => 'tea', 'tenant_id' => $this->tea->id, 'slug' => 'tea']))->assertCreated();
        $this->withHeaders(['X-Tenant' => 'tea', 'X-Ulams-Tenant' => 'tea'])->getJson('/api/admin/entry-points?tenant=tea&slug=tea')->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame($this->coffee->id, EntryPoint::query()->firstOrFail()->tenant_id);
        $this->assertSame(0, EntryPoint::query()->where('tenant_id', $this->tea->id)->count());
    }

    public function testAnotherAcademysEntryPointIsUnknownOnEveryRoute(): void
    {
        $admin = $this->makeAdmin();
        $theirs = $this->entryOf('tea', ['host' => 'sales.tea.example.test']);
        $this->actingAs($admin, 'api');

        $this->getJson('/api/admin/entry-points')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/admin/entry-points/{$theirs['id']}")->assertNotFound();
        $this->patchJson("/api/admin/entry-points/{$theirs['id']}", ['label' => 'Taken over'])->assertNotFound();
        $this->patchJson("/api/admin/entry-points/{$theirs['id']}", ['target' => ['role' => 'x'], 'label' => 'Taken over'])->assertNotFound();
        $this->deleteJson("/api/admin/entry-points/{$theirs['id']}")->assertNotFound();
        $this->getJson('/api/admin/entry-points/audit')->assertOk()->assertJsonCount(0, 'data');

        $row = EntryPoint::query()->findOrFail($theirs['id']);
        $this->assertSame(['Sales', $this->tea->id], [$row->label, $row->tenant_id]);
        // and its host cannot be taken
        $this->postJson('/api/admin/entry-points', $this->body(['host' => 'sales.tea.example.test', 'key' => 'mine']))->assertStatus(422)->assertJsonValidationErrors('host');
    }

    public function testIdsThatAreNotIdsNeverReachThePlatform(): void
    {
        $this->actingAs($this->makeAdmin(), 'api');
        foreach (['1', 'nope', '..%2F..', '01J9Z3K8M2X4Q7R5T6V8W0Y1AB'] as $id) {
            $this->getJson("/api/admin/entry-points/{$id}")->assertNotFound();
            $this->deleteJson("/api/admin/entry-points/{$id}")->assertNotFound();
        }
    }

    public function testDepartmentAndCourseTargetsMustExistInThisAcademy(): void
    {
        if (!Schema::hasTable('groups') || !Schema::hasTable('courses')) {
            $this->markTestSkipped('The test database has no groups or courses table (run php artisan migrate).');
        }
        $this->actingAs($this->makeAdmin(), 'api');
        $group = Group::query()->create(['name' => 'Sales']);

        $this->postJson('/api/admin/entry-points', $this->body(['kind' => 'department', 'target' => ['group_id' => $group->id + 1000]]))
            ->assertStatus(422)->assertJsonValidationErrors('target.group_id');
        $this->postJson('/api/admin/entry-points', $this->body(['kind' => 'course', 'target' => ['course_id' => 999999]]))
            ->assertStatus(422)->assertJsonValidationErrors('target.course_id');
        $this->assertSame(0, EntryPoint::query()->count());

        $this->postJson('/api/admin/entry-points', $this->body(['kind' => 'department', 'target' => ['group_id' => $group->id]]))
            ->assertCreated()->assertJsonPath('data.target.group_id', $group->id);

        $id = EntryPoint::query()->firstOrFail()->id;
        $this->patchJson("/api/admin/entry-points/{$id}", ['target' => ['group_id' => $group->id + 1000]])->assertStatus(422)->assertJsonValidationErrors('target.group_id');
        $this->patchJson("/api/admin/entry-points/{$id}", ['target' => ['course_id' => 1]])->assertStatus(422);
    }

    public function testAddingAnEntryPointDoesNotJoinAnyoneToItsGroup(): void
    {
        if (!Schema::hasTable('groups')) {
            $this->markTestSkipped('The test database has no groups table (run php artisan migrate).');
        }
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $group = Group::query()->create(['name' => 'Sales']);
        $this->actingAs($admin, 'api')->postJson('/api/admin/entry-points', $this->body(['kind' => 'department', 'target' => ['group_id' => $group->id]]))->assertCreated();

        // adding an entry point, or a learner using it, never joins anybody to the group (ADR 0195)
        $this->assertSame(0, $group->users()->count());
        $this->assertFalse($group->belongsToUser($student));
    }

    public function testValidationErrorsAreFieldErrors(): void
    {
        $this->actingAs($this->makeAdmin(), 'api');

        $this->postJson('/api/admin/entry-points', [])->assertStatus(422)->assertJsonValidationErrors(['host', 'key', 'kind', 'label']);
        $this->postJson('/api/admin/entry-points', $this->body(['host' => '10.0.0.1']))->assertStatus(422)->assertJsonValidationErrors('host');
        $this->postJson('/api/admin/entry-points', $this->body(['kind' => 'nope']))->assertStatus(422)->assertJsonValidationErrors('kind');
        $this->postJson('/api/admin/entry-points', $this->body(['key' => 'Bad Key']))->assertStatus(422)->assertJsonValidationErrors('key');
        $this->postJson('/api/admin/entry-points', $this->body(['theme' => ['accent' => 'blue']]))->assertStatus(422)->assertJsonValidationErrors('theme.accent');
        $this->postJson('/api/admin/entry-points', $this->body(['seo' => ['canonical' => 'self']]))->assertStatus(422)->assertJsonValidationErrors('seo.canonical');
        $this->postJson('/api/admin/entry-points', $this->body())->assertCreated();
        $this->postJson('/api/admin/entry-points', $this->body(['host' => 'other.coffee.example.test']))->assertStatus(422)->assertJsonValidationErrors('key');
        $this->assertSame(1, EntryPoint::query()->count());
    }

    public function testActivatingAnUnverifiedHostIsRefused(): void
    {
        config(['ulams_tenancy.entry_trusted_suffixes' => []]);
        $this->actingAs($this->makeAdmin(), 'api');
        $id = $this->postJson('/api/admin/entry-points', $this->body(['host' => 'sales.coffee.example.org']))->assertCreated()
            ->assertJsonPath('data.status', 'pending_dns')->assertJsonPath('data.dns.txt_name', '_ulams-verify.sales.coffee.example.org')->json('data.id');

        $this->patchJson("/api/admin/entry-points/{$id}", ['status' => 'active'])->assertStatus(422)->assertJsonValidationErrors('status');
        $this->assertSame('pending_dns', EntryPoint::query()->findOrFail($id)->status);
    }

    public function testHostKeyAndKindCannotBeChangedAfterCreation(): void
    {
        $this->actingAs($this->makeAdmin(), 'api');
        $id = $this->postJson('/api/admin/entry-points', $this->body())->assertCreated()->json('data.id');

        $this->patchJson("/api/admin/entry-points/{$id}", ['host' => 'evil.example.test', 'key' => 'evil', 'kind' => 'course', 'label' => 'Still sales'])->assertOk();

        $row = EntryPoint::query()->findOrFail($id);
        $this->assertSame(['sales.coffee.example.test', 'sales', 'academy', 'Still sales'], [$row->host, $row->key, $row->kind, $row->label]);
    }

    public function testTheVerificationTokenIsNeverInAListingOfActiveHosts(): void
    {
        $this->actingAs($this->makeAdmin(), 'api');
        $this->postJson('/api/admin/entry-points', $this->body())->assertCreated();

        $this->assertStringNotContainsString('verification_token', $this->getJson('/api/admin/entry-points')->getContent());
    }

    public function testAPlatformFailureIsA503NotAnError500(): void
    {
        $this->app->instance(EntryPointGatewayContract::class, new class implements EntryPointGatewayContract {
            public function list(string $slug): array { throw new \RuntimeException('platform gone'); }
            public function show(string $slug, string $id): array { throw new \RuntimeException('platform gone'); }
            public function create(string $slug, array $data, ?array $actor): array { throw new \RuntimeException('platform gone'); }
            public function update(string $slug, string $id, array $data, ?array $actor): array { throw new \RuntimeException('platform gone'); }
            public function delete(string $slug, string $id, ?array $actor): void { throw new \RuntimeException('platform gone'); }
            public function audit(string $slug, int $limit = 100): array { throw new \RuntimeException('platform gone'); }
        });
        $this->actingAs($this->makeAdmin(), 'api');

        $response = $this->getJson('/api/admin/entry-points')->assertStatus(503);
        $this->assertStringNotContainsString('platform gone', $response->getContent());
    }
}
