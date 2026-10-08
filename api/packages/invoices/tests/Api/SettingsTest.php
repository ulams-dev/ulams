<?php

namespace EscolaLms\Invoices\Tests\Api;

use EscolaLms\Auth\Database\Seeders\AuthPermissionSeeder;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Invoices\EscolaLmsInvoicesServiceProvider;
use EscolaLms\Invoices\Tests\TestCase;
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
        $configKey = EscolaLmsInvoicesServiceProvider::CONFIG_KEY;

        $payUntilDays = $this->faker->numberBetween(7, 30);
        $currencyCode = $this->faker->currencyCode;
        $fraction = $this->faker->currencyCode;
        $symbol = $this->faker->currencyCode;

        $sellerName = $this->faker->company;
        $sellerAddress = $this->faker->address;
        $sellerCode = $this->faker->postcode;
        $sellerVat = $this->faker->swiftBicNumber;
        $sellerPhone = $this->faker->phoneNumber;
        $sellerSwift = $this->faker->swiftBicNumber;

        $this->actingAs($user, 'api')
            ->postJson('/api/admin/config',
                [
                    'config' => [
                        [
                            'key' => "{$configKey}.date.pay_until_days",
                            'value' => $payUntilDays,
                        ],
                        [
                            'key' => "{$configKey}.currency.code",
                            'value' => $currencyCode,
                        ],
                        [
                            'key' => "{$configKey}.currency.fraction",
                            'value' => $fraction,
                        ],
                        [
                            'key' => "{$configKey}.currency.symbol",
                            'value' => $symbol,
                        ],
                        [
                            'key' => "{$configKey}.seller.attributes.name",
                            'value' => $sellerName,
                        ],
                        [
                            'key' => "{$configKey}.seller.attributes.address",
                            'value' => $sellerAddress,
                        ],
                        [
                            'key' => "{$configKey}.seller.attributes.code",
                            'value' => $sellerCode,
                        ],
                        [
                            'key' => "{$configKey}.seller.attributes.vat",
                            'value' => $sellerVat,
                        ],
                        [
                            'key' => "{$configKey}.seller.attributes.phone",
                            'value' => $sellerPhone,
                        ],
                        [
                            'key' => "{$configKey}.seller.attributes.SWIFT",
                            'value' => $sellerSwift,
                        ],
                    ],
                ]
            )
            ->assertOk();

        $this->actingAs($user, 'api')->getJson('/api/admin/config')
            ->assertOk()
            ->assertJsonFragment([
                $configKey => [
                    'date' => [
                        'pay_until_days' => [
                            'full_key' => "$configKey.date.pay_until_days",
                            'key' => 'date.pay_until_days',
                            'public' => true,
                            'rules' => [
                                'required',
                                'integer',
                            ],
                            'value' => $payUntilDays,
                            'readonly' => false,
                        ],
                    ],
                    'currency' => [
                        'code' => [
                            'full_key' => "$configKey.currency.code",
                            'key' => 'currency.code',
                            'public' => true,
                            'rules' => [
                                'required',
                                'string',
                            ],
                            'value' => $currencyCode,
                            'readonly' => false,
                        ],
                        'fraction' => [
                            'full_key' => "$configKey.currency.fraction",
                            'key' => 'currency.fraction',
                            'public' => true,
                            'rules' => [
                                'required',
                                'string',
                            ],
                            'value' => $fraction,
                            'readonly' => false,
                        ],
                        'symbol' => [
                            'full_key' => "$configKey.currency.symbol",
                            'key' => 'currency.symbol',
                            'public' => true,
                            'rules' => [
                                'required',
                                'string',
                            ],
                            'value' => $symbol,
                            'readonly' => false,
                        ],
                    ],
                    'seller' => [
                        'attributes' => [
                            'name' => [
                                'full_key' => "$configKey.seller.attributes.name",
                                'key' => 'seller.attributes.name',
                                'public' => true,
                                'rules' => [
                                    'required',
                                    'string',
                                ],
                                'value' => $sellerName,
                                'readonly' => false,
                            ],
                            'address' => [
                                'full_key' => "$configKey.seller.attributes.address",
                                'key' => 'seller.attributes.address',
                                'public' => true,
                                'rules' => [
                                    'required',
                                    'string',
                                ],
                                'value' => $sellerAddress,
                                'readonly' => false,
                            ],
                            'code' => [
                                'full_key' => "$configKey.seller.attributes.code",
                                'key' => 'seller.attributes.code',
                                'public' => true,
                                'rules' => [
                                    'required',
                                    'string',
                                ],
                                'value' => $sellerCode,
                                'readonly' => false,
                            ],
                            'vat' => [
                                'full_key' => "$configKey.seller.attributes.vat",
                                'key' => 'seller.attributes.vat',
                                'public' => true,
                                'rules' => [
                                    'required',
                                    'string',
                                ],
                                'value' => $sellerVat,
                                'readonly' => false,
                            ],
                            'phone' => [
                                'full_key' => "$configKey.seller.attributes.phone",
                                'key' => 'seller.attributes.phone',
                                'public' => true,
                                'rules' => [
                                    'nullable',
                                    'string',
                                ],
                                'value' => $sellerPhone,
                                'readonly' => false,
                            ],
                            'SWIFT' => [
                                'full_key' => "$configKey.seller.attributes.SWIFT",
                                'key' => 'seller.attributes.SWIFT',
                                'public' => true,
                                'rules' => [
                                    'nullable',
                                    'string',
                                ],
                                'value' => $sellerSwift,
                                'readonly' => false,
                            ],
                        ],
                    ],
                ],
            ]);
    }
}
