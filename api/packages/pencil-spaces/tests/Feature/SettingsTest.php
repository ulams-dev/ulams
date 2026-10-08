<?php

namespace EscolaLms\PencilSpaces\Tests\Feature;

use EscolaLms\Auth\Database\Seeders\AuthPermissionSeeder;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\PencilSpaces\EscolaLmsPencilSpacesServiceProvider;
use EscolaLms\PencilSpaces\Tests\TestCase;
use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use Illuminate\Foundation\Testing\WithFaker;
use EscolaLms\Settings\Database\Seeders\PermissionTableSeeder;
use Illuminate\Support\Facades\Config;

class SettingsTest extends TestCase
{
    use WithFaker, CreatesUsers;

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
        $user = $this->makeAdmin();
        $configKey = EscolaLmsPencilSpacesServiceProvider::CONFIG_KEY;

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
