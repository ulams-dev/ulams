<?php

namespace EscolaLms\Tasks\Tests\Feature;

use EscolaLms\Auth\Database\Seeders\AuthPermissionSeeder;
use EscolaLms\Settings\Database\Seeders\PermissionTableSeeder;
use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use EscolaLms\Tasks\EscolaLmsTasksServiceProvider;
use EscolaLms\Settings\Models\Config as ConfigModel;
use EscolaLms\Tasks\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class SettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(EscolaLmsSettingsServiceProvider::class)) {
            $this->markTestSkipped('Settings package not installed');
        }

        $this->seed(PermissionTableSeeder::class);
        $this->seed(AuthPermissionSeeder::class);
        Config::set('escola_settings.use_database', true);
    }

    public function testAdministrableConfigApi(): void
    {
        $user = config('auth.providers.users.model')::factory()->create();
        $user->guard_name = 'api';
        $user->assignRole('admin');

        $configKey = EscolaLmsTasksServiceProvider::CONFIG_KEY;

        $this->actingAs($user, 'api')
            ->postJson('/api/admin/config',
                [
                    'config' => [
                        [
                            'key' => "{$configKey}.notifications.overdue_period",
                            'value' => 7,
                        ],
                    ]
                ]
            )
            ->assertOk();

        $this->actingAs($user, 'api')->getJson('/api/admin/config')
            ->assertOk()
            ->assertJsonFragment([
                $configKey => [
                    'notifications' => [
                        'overdue_period' => [
                            'full_key' => "$configKey.notifications.overdue_period",
                            'key' => 'notifications.overdue_period',
                            'public' => false,
                            'rules' => [
                                'integer'
                            ],
                            'value' => 7,
                            'readonly' => false,
                        ],
                    ],
                ],
            ]);

        $this->getJson('/api/config')
            ->assertOk()
            ->assertJsonMissing([
                'notifications.overdue_period' => 7,
                'enable' => true
            ]);
    }
}
