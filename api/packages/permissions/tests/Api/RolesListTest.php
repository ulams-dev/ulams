<?php

namespace EscolaLms\Permissions\Tests\Api;

use EscolaLms\Permissions\Models\Template;
use EscolaLms\Permissions\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

class RolesListTest extends TestCase
{
    use DatabaseTransactions;

    public function testAdminCanList(): void
    {
        $this->authenticateAsAdmin();

        $name = 'lorem-ipsum-test';
        $role = Role::findOrCreate($name, 'api');

        $response = $this->actingAs($this->user, 'api')->getJson('/api/admin/roles');

        $response->assertJsonStructure([
            'success',
            'data',
            'message'
        ]);

        $response->assertOk();

        $neededObject = array_filter(
            $response->getData()->data,
            function ($role) use (&$name) {
                return $role->name == $name;
            }
        );

        $this->assertTrue(isset($neededObject));
    }

    public function testFilterAndSortList(): void
    {
        $this->authenticateAsAdmin();

        Role::query()->whereNotIn('name', ['tutor', 'student', 'admin'])->delete();

        $this->actingAs($this->user, 'api')
            ->json('get', '/api/admin/roles', [
                'name' => 'tut'
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'name' => 'tutor'
            ]);

        $response = $this
            ->actingAs($this->user, 'api')
            ->json('get', '/api/admin/roles', [
                'order_by' => 'name',
                'order' => 'DESC'
            ]);

        $this->assertTrue($response->getData()->data[0]->name === 'tutor');
        $this->assertTrue($response->getData()->data[1]->name === 'student');
        $this->assertTrue($response->getData()->data[2]->name === 'admin');

        $response = $this
            ->actingAs($this->user, 'api')
            ->json('get', '/api/admin/roles', [
                'order_by' => 'name',
                'order' => 'ASC'
            ]);

        $this->assertTrue($response->getData()->data[0]->name === 'admin');
        $this->assertTrue($response->getData()->data[1]->name === 'student');
        $this->assertTrue($response->getData()->data[2]->name === 'tutor');

        $response = $this
            ->actingAs($this->user, 'api')
            ->json('get', '/api/admin/roles', [
                'order_by' => 'id',
                'order' => 'DESC'
            ]);

        $this->assertTrue($response->getData()->data[0]->name === 'admin');
        $this->assertTrue($response->getData()->data[1]->name === 'tutor');
        $this->assertTrue($response->getData()->data[2]->name === 'student');

        $response = $this
            ->actingAs($this->user, 'api')
            ->json('get', '/api/admin/roles', [
                'order_by' => 'id',
                'order' => 'ASC'
            ]);

        $this->assertTrue($response->getData()->data[0]->name === 'student');
        $this->assertTrue($response->getData()->data[1]->name === 'tutor');
        $this->assertTrue($response->getData()->data[2]->name === 'admin');
    }

    public function testGuestCannotListRole(): void
    {
        $response = $this->getJson('/api/admin/roles');
        $response->assertUnauthorized();
    }
}
