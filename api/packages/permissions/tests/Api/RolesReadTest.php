<?php

namespace EscolaLms\Permissions\Tests\Api;

use EscolaLms\Permissions\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesReadTest extends TestCase
{
    use DatabaseTransactions;

    public function testAdminCanReadExistingByName(): void
    {
        $this->authenticateAsAdmin();
        $name = 'lorem-ipsum-test';
        $permission_in = "permission_in";
        $permission_out = "permission_out";

        $role = Role::findOrCreate($name, 'api');

        Permission::findOrCreate($permission_in, 'api');
        Permission::findOrCreate($permission_out, 'api');

        $role->givePermissionTo($permission_in);

        $response = $this->actingAs($this->user, 'api')->getJson('/api/admin/roles/' . $name);

        $response->assertOk();

        $per1 = $this->findPermissionByName($response->getData(), $permission_in);
        $per2 = $this->findPermissionByName($response->getData(), $permission_out);

        $this->assertTrue($per1->assigned === true);
        $this->assertTrue($per2->assigned === false);
    }

    public function testGuestCannotReadRole(): void
    {
        $name = 'lorem-ipsum-test';
        $role = Role::findOrCreate($name, 'api');
        $response = $this->getJson('/api/admin/roles/' . $name);
        $response->assertUnauthorized();
    }

    public function testAdminMissingRole(): void
    {
        $this->authenticateAsAdmin();

        $name = 'lorem-ipsum-test-missing';
        $response = $this->actingAs($this->user, 'api')->getJson('/api/admin/roles/' . $name);
        $response->assertNotFound();
    }
}
