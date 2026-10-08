<?php

namespace EscolaLms\Permissions\Tests\Api;

use EscolaLms\Permissions\Events\PermissionRoleRemoved;
use EscolaLms\Templates\Models\Template;
use EscolaLms\Templates\Repository\TemplateRepository;
use EscolaLms\Permissions\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesDeleteTest extends TestCase
{
    use DatabaseTransactions;

    public function testAdminCanDeleteExistingRole(): void
    {
        Event::fake();
        $this->authenticateAsAdmin();
        $name = 'lorem-ipsum-test';

        $role = Role::findOrCreate($name, 'api');


        $response = $this->actingAs($this->user, 'api')->delete('/api/admin/roles/' . $name);
        $response->assertOk();

        $role = Role::where(['name' => $name])->first();

        $this->assertNull($role);
        Event::assertDispatched(PermissionRoleRemoved::class, function ($event) {
            return $event->getUser() && $this->user === $event->getUser() && $event->getRole();
        });
    }

    public function testAdminCanNotDeleteRole(): void
    {
        Event::fake();
        $this->authenticateAsAdmin();
        $name = 'lorem-ipsum-test';

        $response = $this->actingAs($this->user, 'api')->delete('/api/admin/roles/' . $name);
        $response->assertNotFound();
        Event::assertNotDispatched(PermissionRoleRemoved::class, function ($event) {
            return $event->getUser() && $this->user === $event->getUser() && $event->getRole();
        });
    }

    public function testAdminCannotDeleteMissingRole(): void
    {
        $this->authenticateAsAdmin();

        $name = 'lorem-ipsum-test';

        $response = $this->actingAs($this->user, 'api')->delete('/api/admin/roles/' . $name);

        $response->assertStatus(404);
    }

    public function testAdminCannotDeleteAdminRole(): void
    {
        $this->authenticateAsAdmin();

        $name = 'admin';

        $response = $this->actingAs($this->user, 'api')->delete('/api/admin/roles/' . $name);

        $response->assertStatus(403);
    }

    public function testGuestCannotDeleteExistingRole(): void
    {
        $name = 'lorem-ipsum-test';

        Role::findOrCreate($name, 'api');

        $response = $this->deleteJson('/api/admin/roles/' . $name);
        $response->assertUnauthorized();
    }
}
