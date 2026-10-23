<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Permission;
use Ulams\Auth\Enums\AuthPermissionsEnum;
use Ulams\Auth\Models\User;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Tenancy\Jobs\ProvisionTenantJob;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Models\TenantDerivation;
use Ulams\Tenancy\Models\TenantOperation;
use Ulams\Tenancy\Models\TenantTemplate;
use Ulams\Tenancy\Support\TenantNaming;
use Ulams\Tenancy\Tests\TestCase;

/** Tenant templates and derived tenants over the platform API (ADR 0141, amendment): same access rules as the tenant API. */
class PlatformTemplateApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ulams_tenancy.platform_api' => true, 'ulams_tenancy.inherited_env' => ['AI_DRIVER' => 'fake']]);
    }

    private function platformAdmin(): User
    {
        Permission::findOrCreate(AuthPermissionsEnum::PLATFORM_ADMIN, 'api');
        $admin = $this->makeAdmin();
        $admin->givePermissionTo(AuthPermissionsEnum::PLATFORM_ADMIN);

        return $admin;
    }

    private function seedTenant(string $slug): Tenant
    {
        return Tenant::query()->create(array_merge(TenantNaming::newTenantAttributes($slug), ['status' => Tenant::STATUS_ACTIVE]));
    }

    public function testEveryNewRouteIs404WhenTheApiIsOffOrOnATenantHost(): void
    {
        $admin = $this->platformAdmin();
        $calls = [['getJson', '/api/platform/templates'], ['postJson', '/api/platform/templates'], ['getJson', '/api/platform/templates/policy-academy'], ['deleteJson', '/api/platform/templates/policy-academy'], ['getJson', '/api/platform/tenants/acme/derived']];
        config(['ulams_tenancy.platform_api' => false]);
        foreach ($calls as [$method, $url]) {
            $this->actingAs($admin, 'api')->{$method}($url)->assertNotFound();
        }
        $this->getJson('/api/platform/templates')->assertNotFound();
        config(['ulams_tenancy.platform_api' => true, 'ulams_tenancy.tenant_slug' => 'coffee']);
        foreach ($calls as [$method, $url]) {
            $this->actingAs($admin, 'api')->{$method}($url)->assertNotFound();
        }
    }

    public function testNeedsALoginAndThePlatformAdminPermission(): void
    {
        $this->getJson('/api/platform/templates')->assertUnauthorized();
        foreach ([$this->makeStudent(), $this->makeInstructor()] as $user) {
            $this->actingAs($user, 'api')->getJson('/api/platform/templates')->assertForbidden();
            $this->actingAs($user, 'api')->postJson('/api/platform/templates', ['slug' => 'policy-academy'])->assertForbidden();
            $this->actingAs($user, 'api')->getJson('/api/platform/tenants/acme/derived')->assertForbidden();
        }
        $this->assertSame(0, TenantTemplate::query()->count());
    }

    public function testCreateChangeListShowAndRemoveATemplateWithoutExposingItsSettings(): void
    {
        $admin = $this->platformAdmin();

        $created = $this->actingAs($admin, 'api')->postJson('/api/platform/templates', ['slug' => 'policy-academy', 'name' => 'Policy academy', 'theme' => 'coffee', 'accent' => '#C2552D', 'users' => 2, 'env' => ['AI_DRIVER' => 'fake']])->assertCreated();
        $created->assertJsonPath('data.slug', 'policy-academy')->assertJsonPath('data.env_override_keys', ['AI_DRIVER'])->assertJsonPath('data.users', 2);
        $this->assertStringNotContainsString('fake', $created->getContent());

        $this->actingAs($admin, 'api')->postJson('/api/platform/templates', ['slug' => 'policy-academy', 'theme' => 'nightsky'])->assertOk()->assertJsonPath('data.theme', 'nightsky')->assertJsonPath('data.accent', '#C2552D');
        $this->actingAs($admin, 'api')->getJson('/api/platform/templates')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin, 'api')->getJson('/api/platform/templates/policy-academy')->assertOk()->assertJsonPath('data.name', 'Policy academy');
        $this->actingAs($admin, 'api')->getJson('/api/platform/templates/nope')->assertNotFound();

        $this->actingAs($admin, 'api')->deleteJson('/api/platform/templates/policy-academy')->assertOk();
        $this->actingAs($admin, 'api')->deleteJson('/api/platform/templates/policy-academy')->assertNotFound();
    }

    public function testInvalidTemplatesAreRefused(): void
    {
        $admin = $this->platformAdmin();
        foreach ([[], ['slug' => 'Bad Slug'], ['slug' => 'ok-slug', 'accent' => 'red'], ['slug' => 'ok-slug', 'users' => 99], ['slug' => 'ok-slug', 'name' => '<b>'], ['slug' => 'ok-slug', 'env' => ['DB_PASSWORD' => 'x']], ['slug' => 'ok-slug', 'env' => ['AI_DRIVER' => '']]] as $body) {
            $this->actingAs($admin, 'api')->postJson('/api/platform/templates', $body)->assertStatus(422);
        }
        $this->assertSame(0, TenantTemplate::query()->count());
    }

    public function testATenantCanBeCreatedFromATemplateForAPurposeAndIsRecorded(): void
    {
        Bus::fake([ProvisionTenantJob::class]);
        $admin = $this->platformAdmin();
        $this->seedTenant('acme');
        $this->actingAs($admin, 'api')->postJson('/api/platform/templates', ['slug' => 'policy-academy', 'name' => 'Policy academy', 'theme' => 'coffee', 'users' => 2])->assertCreated();

        $res = $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', ['slug' => 'acmesales', 'template' => 'policy-academy', 'accent' => '#112233', 'derived_from' => 'acme', 'purpose' => 'policy_department', 'reference' => 'p1:v1'])->assertStatus(202);

        $tenant = Tenant::query()->where('slug', 'acmesales')->firstOrFail();
        $this->assertSame(['Policy academy', 'coffee', '#112233'], [$tenant->name, $tenant->theme, $tenant->accent]);
        $row = TenantDerivation::query()->sole();
        $this->assertSame(['acme', 'policy_department', 'p1:v1', 'policy-academy', 'active'], [$row->parent_slug, $row->purpose, $row->reference, $row->template_slug, $row->status]);
        $this->assertSame($admin->email, $row->requested_by);
        Bus::assertDispatched(ProvisionTenantJob::class, fn ($job) => $job->users === 2);
        $operation = TenantOperation::query()->findOrFail($res->json('data.operation.id'));
        $this->assertSame('p1:v1', $operation->input['reference']);

        $this->actingAs($admin, 'api')->getJson('/api/platform/tenants/acme/derived')->assertOk()
            ->assertJsonPath('data.0.tenant', 'acmesales')->assertJsonPath('data.0.reference', 'p1:v1')->assertJsonPath('data.0.requested_by', $admin->email);
    }

    public function testTheQuotaTheReferenceAndAnUnknownTemplateAreRefusedBeforeAnythingIsCreated(): void
    {
        Bus::fake([ProvisionTenantJob::class]);
        config(['ulams_tenancy.derived.max_per_parent' => 1]);
        $admin = $this->platformAdmin();
        $this->seedTenant('acme');
        $body = fn (string $slug, string $reference, array $extra = []) => ['slug' => $slug, 'derived_from' => 'acme', 'purpose' => 'policy_department', 'reference' => $reference] + $extra;

        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', $body('acmesales', 'r1'))->assertStatus(202);
        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', $body('acmetwo', 'r2'))->assertStatus(409)->assertJsonPath('message', fn ($m) => str_contains($m, 'the limit is 1'));
        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', $body('acmeother', 'r1'))->assertStatus(409);
        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', $body('acmetri', 'r3', ['template' => 'nope']))->assertStatus(409);
        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', ['slug' => 'acmefour', 'template' => 'nope'])->assertStatus(422);
        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', ['slug' => 'acmefive', 'derived_from' => 'acme'])->assertStatus(422);

        $this->assertSame(['acme', 'acmesales'], Tenant::query()->orderBy('slug')->pluck('slug')->all());
        $this->assertSame(1, TenantDerivation::query()->count());
    }

    public function testATenantWithDerivedTenantsIsNotDeletedThroughTheApi(): void
    {
        Bus::fake([ProvisionTenantJob::class]);
        $admin = $this->platformAdmin();
        $this->seedTenant('acme');
        $this->actingAs($admin, 'api')->postJson('/api/platform/tenants', ['slug' => 'acmesales', 'derived_from' => 'acme', 'purpose' => 'policy_department', 'reference' => 'r1'])->assertStatus(202);

        $this->actingAs($admin, 'api')->deleteJson('/api/platform/tenants/acme', ['confirm' => 'acme'])->assertStatus(409)
            ->assertJsonPath('code', 'has_derived_tenants')->assertJsonPath('data.derived', ['acmesales']);
        $this->assertSame(0, TenantOperation::query()->where('kind', 'delete')->count());
    }
}
