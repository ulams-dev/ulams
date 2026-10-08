<?php

namespace EscolaLms\Recommender\Tests\Feature;

use EscolaLms\Auth\Database\Seeders\AuthPermissionSeeder;
use EscolaLms\Recommender\EscolaLmsRecommenderServiceProvider;
use EscolaLms\Recommender\Tests\TestCase;
use EscolaLms\Settings\Database\Seeders\PermissionTableSeeder;
use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class SettingsTest extends TestCase
{
    use WithFaker;

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
        Http::fakeSequence()
            ->push([])
            ->push([]);

        $user = config('auth.providers.users.model')::factory()->create();
        $user->guard_name = 'api';
        $user->assignRole('admin');

        $configKey = EscolaLmsRecommenderServiceProvider::CONFIG_KEY;

        $enabled = $this->faker->boolean;
        $apiUrl = $this->faker->url;
        $courseModel = '{"data": "course"}';
        $exerciseModel = '{"data": "exercise"}';
        $satisfactionModels = [
            ['model' => 'prod_elasticnet', 'enabled' => true],
            ['model' => 'retrained_ridge3', 'enabled' => false],
            ['model' => 'retrained_rf13', 'enabled' => false],
        ];

        $this->actingAs($user, 'api')
            ->postJson('/api/admin/config',
                [
                    'config' => [
                        [
                            'key' => $configKey . '.enabled',
                            'value' => $enabled,
                        ],
                        [
                            'key' => $configKey . '.api_url',
                            'value' => $apiUrl,
                        ],
                        [
                            'key' => $configKey . '.course_model',
                            'value' => $courseModel,
                        ],
                        [
                            'key' => $configKey . '.exercise_model',
                            'value' => $exerciseModel,
                        ],
                        [
                            'key' => $configKey . '.satisfaction_models',
                            'value' => $satisfactionModels,
                        ],
                    ]
                ]
            )
            ->assertOk();

        $this->actingAs($user, 'api')->getJson('/api/admin/config')
            ->assertOk()
            ->assertJsonFragment([
                $configKey => [
                    'enabled' => [
                        'full_key' => $configKey . '.enabled',
                        'key' => 'enabled',
                        'public' => true,
                        'rules' => [
                            'required', 'boolean'
                        ],
                        'value' => $enabled,
                        'readonly' => false,
                    ],
                    'api_url' => [
                        'full_key' => $configKey . '.api_url',
                        'key' => 'api_url',
                        'public' => false,
                        'rules' => [
                            'string'
                        ],
                        'value' => $apiUrl,
                        'readonly' => false,
                    ],
                    'course_model' => [
                        'full_key' => $configKey . '.course_model',
                        'key' => 'course_model',
                        'public' => false,
                        'rules' => [
                            'string'
                        ],
                        'value' => $courseModel,
                        'readonly' => false,
                    ],
                    'exercise_model' => [
                        'full_key' => $configKey . '.exercise_model',
                        'key' => 'exercise_model',
                        'public' => false,
                        'rules' => [
                            'string'
                        ],
                        'value' => $exerciseModel,
                        'readonly' => false,
                    ],
                    'satisfaction_models' => [
                        'full_key' => $configKey . '.satisfaction_models',
                        'key' => 'satisfaction_models',
                        'public' => false,
                        'rules' => [
                            'array'
                        ],
                        'value' => $satisfactionModels,
                        'readonly' => false,
                    ],
                ],
            ]);

        $this->getJson('/api/config')
            ->assertOk()
            ->assertJsonMissing([
                'api_url' => $apiUrl,
                'course_model' => $courseModel,
                'exercise_model' => $exerciseModel,
            ]);
    }
}

