<?php

namespace EscolaLms\Video\Tests\Feature;

use EscolaLms\Auth\Database\Seeders\AuthPermissionSeeder;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Settings\Database\Seeders\PermissionTableSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use EscolaLms\Video\Providers\SettingsServiceProvider;
use EscolaLms\Video\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class SettingsTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers, WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\EscolaLms\Settings\EscolaLmsSettingsServiceProvider::class)) {
            $this->markTestSkipped('Settings package not installed');
        }

        $this->seed(PermissionTableSeeder::class);
        $this->seed(AuthPermissionSeeder::class);
        Config::set('escola_settings.use_database', true);
        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
    }

    protected function tearDown(): void
    {
        \EscolaLms\Settings\Models\Config::truncate();
    }

    public function testAdministrableConfigApi(): void
    {
        $configKey = SettingsServiceProvider::CONFIG_KEY;

        $this->response = $this->actingAs($this->user, 'api')->json(
            'POST',
            '/api/admin/config',
            [
                'config' => [
                    [
                        'key' => "{$configKey}.bitrates",
                        'value' => [
                            [
                                "kiloBitrate" => 500,
                                "scale" => "500:500"
                            ],
                            [
                                "kiloBitrate" => 500,
                                "scale" => "1280:720"
                            ]
                        ],
                    ],
                    [
                        'key' => "{$configKey}.enable",
                        'value' => true,
                    ],
                    [
                        'key' => "{$configKey}.non_strict_value",
                        'value' => true,
                    ],
                ]
            ]
        );
        $this->response->assertOk();

        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/admin/config'
        );

        $this->response->assertOk();
        $this->response->assertJsonFragment([
            $configKey => [
                'bitrates' => [
                    'full_key' => "$configKey.bitrates",
                    'key' => 'bitrates',
                    'public' => false,
                    'rules' => [
                        'array'
                    ],
                    'value' => [
                        [
                            'kiloBitrate' => 500,
                            'scale' => '500:500'
                        ],
                        [
                            'kiloBitrate' => 500,
                            'scale' => '1280:720'
                        ]
                    ],
                    'readonly' => false,
                ],
                'enable' => [
                    'full_key' => "$configKey.enable",
                    'key' => 'enable',
                    'public' => false,
                    'rules' => [
                        'boolean'
                    ],
                    'value' => true,
                    'readonly' => false,
                ],
                'non_strict_value' => [
                    'full_key' => "$configKey.non_strict_value",
                    'key' => 'non_strict_value',
                    'public' => false,
                    'rules' => [
                        'boolean'
                    ],
                    'value' => true,
                    'readonly' => false,
                ]
            ],
        ]);

        $this->response = $this->json(
            'GET',
            '/api/config'
        );

        $this->response->assertOk();
        $this->response->assertJsonMissing([
            'bitrates' => [
                [
                    'kiloBitrate' => 500,
                    'scale' => '500:500'
                ],
                [
                    'kiloBitrate' => 500,
                    'scale' => '1280:720'
                ]
            ],
            'enable' => true,
            'non_strict_value' => true
        ]);

    }
}
