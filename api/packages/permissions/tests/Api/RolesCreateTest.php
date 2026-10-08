<?php

namespace EscolaLms\Permissions\Tests\Api;

use EscolaLms\Permissions\Events\PermissionRoleChanged;
use EscolaLms\Permissions\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class RolesCreateTest extends TestCase
{
    use DatabaseTransactions;

    public function testAdminCanCreateRole(): void
    {
        Event::fake();
        $this->authenticateAsAdmin();
        $name = "lkdfsj lfds lkjfsd lkjsl87923 dfs";
        $response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/roles',
            [
                'name' => $name
            ]
        );

        $response->assertStatus(200);

        $this->assertEquals($response->getData()->data->name, Str::slug($name));
        Event::assertDispatched(PermissionRoleChanged::class, function ($event) {
            return $event->getUser() && $this->user === $event->getUser() && $event->getRole();
        });
    }

    public function testTutorCantCreateRole(): void
    {
        $user = config('auth.providers.users.model')::factory()->create();
        $user->guard_name = 'api';
        $user->assignRole('tutor');

        $name = "lkdfsj lfds lkjfsd lkjsl87923 dfs";
        $response = $this->actingAs($user, 'api')->postJson(
            '/api/admin/roles',
            [
                'name' => $name
            ]
        );

        $response->assertStatus(403);
    }

    public function testAdminCannotCreateRoleWithoutName(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/roles',
            []
        );
        $response->assertStatus(422);
    }

    public function testGuestCannotCreateRole(): void
    {
        $response = $this->postJson(
            '/api/admin/roles',
            [
                'name' => 'name'
            ]
        );
        $response->assertUnauthorized();
    }
}
