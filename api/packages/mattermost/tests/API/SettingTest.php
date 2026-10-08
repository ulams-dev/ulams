<?php

namespace EscolaLms\Mattermost\Tests\API;

use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Mattermost\Enum\PackageStatusEnum;
use EscolaLms\Mattermost\Providers\SettingsServiceProvider;
use EscolaLms\Mattermost\Tests\TestCase;
use EscolaLms\Settings\Database\Seeders\PermissionTableSeeder;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Config;

class SettingTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\EscolaLms\Settings\EscolaLmsSettingsServiceProvider::class)) {
            $this->markTestSkipped('Settings package not installed');
        }

        $this->seed(PermissionTableSeeder::class);
        Config::set('escola_settings.use_database', true);
        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
        $this->mock->reset();
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
                        'key' => "{$configKey}.package_status",
                        'value' => PackageStatusEnum::DISABLED,
                    ],
                    [
                        'key' => "{$configKey}.servers.default.host",
                        'value' => 'localhost',
                    ],
                    [
                        'key' => "{$configKey}.servers.default.login",
                        'value' => 'new_login',
                    ],
                    [
                        'key' => "{$configKey}.servers.default.password",
                        'value' => 'strong_password',
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
                'package_status' => [
                    'full_key' => "$configKey.package_status",
                    'key' => 'package_status',
                    'rules' => [
                        'required',
                        'string',
                        'in:' . implode(',', PackageStatusEnum::getValues())
                    ],
                    'public' => false,
                    'value' => PackageStatusEnum::DISABLED,
                    'readonly' => false,
                ],
                'servers' => [
                    'default' => [
                        'host' => [
                            'full_key' => "$configKey.servers.default.host",
                            'key' => 'servers.default.host',
                            'rules' => [
                                'required',
                                'string'
                            ],
                            'public' => true,
                            'value' => 'localhost',
                            'readonly' => false,
                        ],
                        'login' => [
                            'full_key' => "$configKey.servers.default.login",
                            'key' => 'servers.default.login',
                            'rules' => [
                                'required',
                                'string'
                            ],
                            'public' => false,
                            'value' => 'new_login',
                            'readonly' => false,
                        ],
                        'password' => [
                            'full_key' => "$configKey.servers.default.password",
                            'key' => 'servers.default.password',
                            'rules' => [
                                'required',
                                'string'
                            ],
                            'public' => false,
                            'value' => 'strong_password',
                            'readonly' => false,
                        ],
                    ],
                ],
            ],
        ]);

        $this->response = $this->json(
            'GET',
            '/api/config'
        );
        $this->response->assertJsonFragment([
            $configKey => [
                'servers' => [
                    'default' => [
                        'host' => 'localhost',
                    ],
                ],
            ]
        ]);

        $this->response->assertJsonMissing([
            'package_status' => PackageStatusEnum::DISABLED,
            'login' => 'new_login',
            'password' => 'strong_password',
        ]);
    }
}
