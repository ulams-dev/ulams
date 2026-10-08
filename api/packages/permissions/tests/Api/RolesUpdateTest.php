<?php

namespace EscolaLms\Permissions\Tests\Api;

use EscolaLms\Permissions\Events\PermissionRoleChanged;
use EscolaLms\Permissions\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesUpdateTest extends TestCase
{
    use DatabaseTransactions;

    public function testAdminCanUpdateExistingByName(): void
    {
        $this->authenticateAsAdmin();
        $name = 'lorem-ipsum-test';
        $permission_in = "permission_in";
        $permission_out = "permission_out";

        Role::findOrCreate($name, 'api');

        Permission::findOrCreate($permission_in, 'api');
        Permission::findOrCreate($permission_out, 'api');

        $response = $this->actingAs($this->user, 'api')->getJson('/api/admin/roles/' . $name);

        $response->assertOk();


        $per1 = $this->findPermissionByName($response->getData(), $permission_in);
        $per2 = $this->findPermissionByName($response->getData(), $permission_out);

        $this->assertTrue($per1->assigned === false);
        $this->assertTrue($per2->assigned === false);

        Event::fake();
        $response = $this->actingAs($this->user, 'api')->patchJson('/api/admin/roles/' . $name, [
            'permissions' => [
                'permission_in', 'permission_out'
            ]
        ]);
        $response->assertOk();


        $this->assertTrue($per1->assigned === false);
        $this->assertTrue($per2->assigned === false);
        Event::assertDispatched(PermissionRoleChanged::class, function ($event) {
            return $event->getUser() && $this->user === $event->getUser() && $event->getRole();
        });
    }

    public function testAdminCannotUpdateMissingRole(): void
    {
        $this->authenticateAsAdmin();
        $name = 'lorem-ipsum-test';
        $permission_in = "permission_in";
        $permission_out = "permission_out";

        Role::findOrCreate($name, 'api');

        Permission::findOrCreate($permission_in, 'api');


        $response = $this->actingAs($this->user, 'api')->patchJson('/api/admin/roles/' . $name, [
            'permissions' => [
                'permission_in', 'permission_out'
            ]
        ]);

        //"There is no permission named `permission_out` for guard `api`."

        $response->assertNotFound();
    }

    public function testAdminThrowUpdateRole(): void
    {
        $this->authenticateAsAdmin();
        $name = 'admin';
        $permission_in = "permission_in";
        Role::findOrCreate($name, 'api');
        Permission::findOrCreate($permission_in, 'api');
        $response = $this->actingAs($this->user, 'api')->patchJson('/api/admin/roles/' . $name, [
            'permissions' => [
                'permission_in'
            ]
        ]);
        $response->assertNotFound();
        $response->assertJsonFragment([
            'success' => false,
            'message' =>  __('Admin role cannot be updated'),
        ]);
    }

    public function testGuestCannotUpdateRole(): void
    {
        $name = 'lorem-ipsum-test';
        $role = Role::findOrCreate($name, 'api');
        $response = $this->patchJson('/api/admin/roles/' . $name, [
            'permissions' => [
                'permission_in', 'permission_out'
            ]
        ]);
        $response->assertUnauthorized();
    }

    public function testAdminMissingRole(): void
    {
        $this->authenticateAsAdmin();

        $name = 'lorem-ipsum-test-missing';
        $response = $this->actingAs($this->user, 'api')->patchJson('/api/admin/roles/' . $name, [
            'permissions' => [
                'permission_in', 'permission_out'
            ]
        ]);

        $response->assertNotFound();
    }
}
