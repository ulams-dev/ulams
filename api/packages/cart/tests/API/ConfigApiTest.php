<?php

namespace Ulams\Cart\Tests\API;

use Ulams\Cart\UlamsCartServiceProvider;
use Ulams\Cart\Tests\TestCase;
use Ulams\Settings\Database\Seeders\PermissionTableSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;

class ConfigApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\Ulams\Settings\UlamsSettingsServiceProvider::class)) {
            $this->markTestSkipped();
        }

        $this->seed(PermissionTableSeeder::class);

        Config::set('ulams_settings.use_database', true);

        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
    }

    public function testAdministrableConfigApi()
    {
        Config::set(UlamsCartServiceProvider::CONFIG_KEY . '.min_product_price', 0);
        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/admin/config'
        );

        $this->response->assertOk();
        $this->response->assertJsonFragment([
            'min_product_price' => [
                'full_key' => 'ulams_cart.min_product_price',
                'key' => 'min_product_price',
                'rules' => [
                    'required',
                    'numeric',
                    'min:0',
                ],
                'value' => 0,
                'readonly' => false,
                'public' => true,
            ],
        ]);

        $this->response = $this->actingAs($this->user, 'api')->json(
            'POST',
            '/api/admin/config',
            [
                'config' => [
                    [
                        'key' => 'ulams_cart.min_product_price',
                        'value' => 10,
                    ],
                ]
            ]
        );
        $this->response->assertOk();

        $this->response = $this->json(
            'GET',
            '/api/config'
        );
        $this->response->assertOk();
        $this->response->assertJsonFragment([
            'ulams_cart' => [
                'min_product_price' => 10,
            ]
        ]);
    }
}
