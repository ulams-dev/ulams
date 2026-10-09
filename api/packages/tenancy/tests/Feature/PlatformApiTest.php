<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Tenancy\Enums\TenancyPermissionsEnum;
use Ulams\Tenancy\Jobs\ProvisionTenantJob;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Support\TenantNaming;
use Ulams\Tenancy\Tests\TestCase;

/** The platform tenant API (ADR 0078): off by default, platform hosts only, one permission. */
class PlatformApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['ulams_tenancy.platform_api' => true]);
        Permission::findOrCreate(TenancyPermissionsEnum::TENANCY_MANAGE, 'api');
    }

    private function operator()
    {
        $user = $this->makeAdmin();
        $user->givePermissionTo(TenancyPermissionsEnum::TENANCY_MANAGE);

        return $user;
    }

    public function testItIsOffUnlessSwitchedOn(): void
    {
        config(['ulams_tenancy.platform_api' => false]);
        $this->actingAs($this->operator(), 'api')->postJson('/api/platform/tenants', ['slug' => 'newsite'])->assertNotFound();
        Queue::assertNothingPushed();
    }

    public function testItDoesNotExistOnTenantHosts(): void
    {
        config(['ulams_tenancy.tenant_slug' => 'coffee']);
        $this->actingAs($this->operator(), 'api')->postJson('/api/platform/tenants', ['slug' => 'newsite'])->assertNotFound();
        $this->actingAs($this->operator(), 'api')->getJson('/api/platform/tenants/coffee')->assertNotFound();
        Queue::assertNothingPushed();
    }

    public function testGuestsAndPeopleWithoutThePermissionAreRefused(): void
    {
        $this->postJson('/api/platform/tenants', ['slug' => 'newsite'])->assertUnauthorized();
        $this->actingAs($this->makeAdmin(), 'api')->postJson('/api/platform/tenants', ['slug' => 'newsite'])->assertForbidden();
        $this->actingAs($this->makeAdmin(), 'api')->getJson('/api/platform/tenants/newsite')->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function testCreatingQueuesTheProvisioningAndReportsProgress(): void
    {
        $operator = $this->operator();
        $response = $this->actingAs($operator, 'api')->postJson('/api/platform/tenants', ['slug' => 'newsite', 'name' => 'New Site', 'theme' => 'nightsky', 'accent' => '#FFAA00'])
            ->assertStatus(202)->assertJsonPath('data.slug', 'newsite')->assertJsonPath('data.status', 'provisioning');
        $this->assertCount(9, $response->json('data.steps'));
        Queue::assertPushed(ProvisionTenantJob::class, fn (ProvisionTenantJob $job) => $job->slug === 'newsite');
        $this->assertDatabaseHas('tenants', ['slug' => 'newsite', 'name' => 'New Site', 'theme' => 'nightsky', 'accent' => '#FFAA00']);

        $tenant = Tenant::query()->where('slug', 'newsite')->first();
        $tenant->markCompleted('database');
        $this->actingAs($operator, 'api')->getJson('/api/platform/tenants/newsite')->assertOk()
            ->assertJsonPath('data.steps.0', ['step' => 'database', 'state' => 'done'])
            ->assertJsonPath('data.steps.1', ['step' => 'bucket', 'state' => 'pending'])
            ->assertJsonPath('data.urls', null);
    }

    public function testASiteThatIsAlreadyActiveCannotBeCreatedAgain(): void
    {
        Tenant::query()->create(['status' => Tenant::STATUS_ACTIVE] + TenantNaming::newTenantAttributes('taken'));
        $this->actingAs($this->operator(), 'api')->postJson('/api/platform/tenants', ['slug' => 'taken'])->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function testBadInputIsRefused(): void
    {
        $operator = $this->operator();
        $this->actingAs($operator, 'api')->postJson('/api/platform/tenants', ['slug' => 'Bad Slug!'])->assertStatus(422);
        $this->actingAs($operator, 'api')->postJson('/api/platform/tenants', ['slug' => 'okslug', 'accent' => 'red'])->assertStatus(422);
        $this->actingAs($operator, 'api')->getJson('/api/platform/tenants/unknown')->assertNotFound();
    }
}
