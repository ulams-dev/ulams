<?php

namespace Ulams\PencilSpaces\Tests\Feature;

use Ulams\Auth\Database\Seeders\AuthPermissionSeeder;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\PencilSpaces\UlamsPencilSpacesServiceProvider;
use Ulams\PencilSpaces\Tests\TestCase;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Illuminate\Foundation\Testing\WithFaker;
use Ulams\Settings\Database\Seeders\PermissionTableSeeder;
use Illuminate\Support\Facades\Config;

class SettingsTest extends TestCase
{
    use WithFaker, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(UlamsSettingsServiceProvider::class)) {
            $this->markTestSkipped('Settings package not installed');
        }

        $this->seed(PermissionTableSeeder::class);
        $this->seed(AuthPermissionSeeder::class);
        Config::set('ulams_settings.use_database', true);
    }

    public function testAdministrableConfigApi(): void
    {
        $user = $this->makeAdmin();
        $configKey = UlamsPencilSpacesServiceProvider::CONFIG_KEY;

        $apiUrl = $this->faker->url;
        $apiKey = $this->faker->uuid;

        $this->actingAs($user, 'api')
            ->postJson('/api/admin/config',
                [
                    'config' => [
                        [
                            'key' => "{$configKey}.api_url",
                            'value' => $apiUrl,
                        ],
                        [
                            'key' => "{$configKey}.api_key",
                            'value' => $apiKey,
                        ],
                    ],
                ]
            )
            ->assertOk();

        $this->actingAs($user, 'api')->getJson('/api/admin/config')
            ->assertOk()
            ->assertJsonFragment([
                $configKey => [
                    'api_url' => [
                        'full_key' => "$configKey.api_url",
                        'key' => 'api_url',
                        'public' => false,
                        'rules' => [
                            'string'
                        ],
                        'value' => $apiUrl,
                        'readonly' => false,
                    ],
                    'api_key' => [
                        'full_key' => "$configKey.api_key",
                        'key' => 'api_key',
                        'public' => false,
                        'rules' => [
                            'string'
                        ],
                        'value' => $apiKey,
                        'readonly' => false,
                    ],
                ],
            ]);
    }

}
