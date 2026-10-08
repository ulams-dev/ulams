<?php

namespace EscolaLms\BulkNotifications\Tests\Feature;

use EscolaLms\Auth\Database\Seeders\AuthPermissionSeeder;
use EscolaLms\BulkNotifications\EscolaLmsBulkNotificationsServiceProvider;
use EscolaLms\BulkNotifications\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Settings\Database\Seeders\PermissionTableSeeder;
use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;

class SettingsTest extends TestCase
{
    use CreatesUsers, WithFaker;

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

        $configKey = EscolaLmsBulkNotificationsServiceProvider::CONFIG_KEY;

        $pushServiceAccount = json_encode([
            "type" => "service_account",
            "project_id" => "project-id",
            "private_key_id" => "123",
            "private_key" => "123",
            "client_email" => "client_email@gserviceaccount.com",
            "client_id" => "123",
            "auth_uri" => "https://auth_uri",
            "token_uri" => "https://token_uri",
            "auth_provider_x509_cert_url" => "https://auth_provider_x509_cert_url",
            "client_x509_cert_url" => "https://client_x509_cert_url",
            "universe_domain" => "universe_domain.com",
        ]);
        $pushBaseRedirectUrl = $this->faker->url;

        $this->actingAs($user, 'api')
            ->postJson('/api/admin/config',
                [
                    'config' => [
                        [
                            'key' => "{$configKey}.push.service_account",
                            'value' => $pushServiceAccount,
                        ],
                        [
                            'key' => "{$configKey}.push.base_redirect_url",
                            'value' => $pushBaseRedirectUrl,
                        ],
                    ]
                ]
            )
            ->assertOk();

        $this->actingAs($user, 'api')->getJson('/api/admin/config')
            ->assertOk()
            ->assertJsonFragment([
                $configKey => [
                    'push' => [
                        'service_account' => [
                            'full_key' => "$configKey.push.service_account",
                            'key' => 'push.service_account',
                            'public' => false,
                            'rules' => [
                                'json'
                            ],
                            'value' => $pushServiceAccount,
                            'readonly' => false,
                        ],
                        'base_redirect_url' => [
                            'full_key' => "$configKey.push.base_redirect_url",
                            'key' => 'push.base_redirect_url',
                            'public' => false,
                            'rules' => [
                                'string'
                            ],
                            'value' => $pushBaseRedirectUrl,
                            'readonly' => false,
                        ]
                    ],
                ],
            ]);

        $this->getJson('/api/config')
            ->assertOk()
            ->assertJsonMissing([
                'push.service_account' => $pushServiceAccount,
                'push.base_redirect_url' => $pushBaseRedirectUrl,
                'enable' => true
            ]);
    }
}
