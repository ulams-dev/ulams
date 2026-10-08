<?php

namespace Ulams\Settings\Tests\API;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Settings\Database\Seeders\DatabaseSeeder;
use Ulams\Settings\Database\Seeders\PermissionTableSeeder;
use Ulams\Settings\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SettingsTutorTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionTableSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->user = $this->makeInstructor();
    }

    public function test_settings_index()
    {
        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/admin/settings'
        );
        $this->response->assertOk();

        $this->response->assertJsonFragment(['data' => ["USD", "EUR"]]);
        $this->response->assertJsonFragment(['data' => 'Lorem IPSUM']);
        $this->response->assertJsonFragment(['current_page' => 1]);
    }
}
