<?php

namespace Ulams\TopicTypeGift\Tests\Api\Admin;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\TopicTypeGift\Providers\SettingsServiceProvider;
use Ulams\TopicTypeGift\Tests\TestCase;
use Ulams\Settings\Database\Seeders\PermissionTableSeeder as SettingsPermissionSeeder;
use Illuminate\Support\Facades\Config;

class AdminAdministrableConfigTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsPermissionSeeder::class);
        Config::set('ulams_settings.use_database', true);
    }

    public function testAdministrableConfigApi(): void
    {
        $configKey = SettingsServiceProvider::KEY;

        $this->actingAs($this->makeAdmin(), 'api')->postJson('api/admin/config', [
            'config' => [
                [
                    'key' => "{$configKey}.max_quiz_time",
                    'value' => 123,
                ],
            ],
        ])->assertOk();

        $this->actingAs($this->makeAdmin(), 'api')->getJson('api/admin/config')
            ->assertOk()
            ->assertJsonFragment([
                $configKey => [
                    'max_quiz_time' => [
                        'full_key' => "$configKey.max_quiz_time",
                        'key' => 'max_quiz_time',
                        'rules' => [
                            'required',
                            'integer',
                            'min:1',
                        ],
                        'public' => false,
                        'value' => 123,
                        'readonly' => false,
                    ],
                ],
            ]);

        $this->getJson('api/config')
            ->assertJsonMissing(['max_quiz_time']);
    }
}
