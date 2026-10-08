<?php

namespace Tests\APIs;

use EscolaLms\Settings\Database\Seeders\DatabaseSeeder;
use EscolaLms\Settings\Database\Seeders\PermissionTableSeeder;
use EscolaLms\Settings\Enums\SettingTypes;
use EscolaLms\Settings\Models\Setting;
use EscolaLms\Settings\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SettingsAdminTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {

        parent::setUp();
        $this->seed(PermissionTableSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
    }

    /**
     * @test
     */
    public function test_admin_fetch()
    {

        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/admin/settings'
        );
        $this->response->assertOk();

        $this->response->assertJsonFragment(['data' => ["USD", "EUR"]]);
        $this->response->assertJsonFragment(['data' => 'Lorem IPSUM']);
        $this->response->assertJsonFragment(['current_page' => 1]);
    }

    public function test_admin_fetch_without_pagination()
    {
        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/admin/settings',
            ['per_page' => -1]
        );
        $this->response->assertOk();
        $this->response->assertJsonMissing(['current_page' => 1]);
    }

    public function test_admin_fetch_paginate()
    {

        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/admin/settings?page=2'
        );
        $this->response->assertOk();

        $this->response->assertJsonFragment(['current_page' => 2]);
    }

    public function test_admin_fetch_search()
    {

        Setting::firstOrCreate([
            'group' => 'images',
            'key' => 'tutor',
            'value' => "tutor_avatar.jpg",
            'public' => true,
            'enumerable' => true,
            'type' => 'image'
        ]);

        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/admin/settings?group=images'
        );
        $this->response->assertOk();

        $this->response->assertJsonFragment(['group' => 'images']);

        Setting::firstOrCreate([
            'group' => 'images',
            'key' => 'test_test_test',
            'value' => "tutor_avatar.jpg",
            'public' => true,
            'enumerable' => true,
            'type' => 'image'
        ]);

        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/admin/settings?group=images',
            [
                'key' => 'test_test_test'
            ]);

        $this->response
            ->assertOk()
            ->assertJsonFragment(['key' => 'test_test_test'])
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_show()
    {

        $setting = Setting::first();
        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/admin/settings/' . $setting->id
        );
        $this->response->assertOk();
    }

    public function test_not_found_show()
    {

        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/admin/settings/9999'
        );
        $this->response->assertNotFound();
    }

    public function test_admin_groups()
    {

        $setting = Setting::first();
        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/admin/settings/groups'
        );
        $this->response->assertOk();

        $this->assertTrue(in_array($setting->group, $this->response->getData()->data));
    }

    public function test_admin_update()
    {
        $setting = Setting::first();

        $input = [
            'group' => 'config',
            'key' => 'app.env',
            'value' => "app.env",
            'public' => true,
            'enumerable' => true,
            'type' => 'config'
        ];

        $this->response = $this->actingAs($this->user, 'api')->json(
            'PUT',
            '/api/admin/settings/' . $setting->id,
            $input
        );

        $this->response->assertOk();

        $this->assertEquals($input['value'], $this->response->getData()->data->value);


        $this->response = $this->actingAs($this->user, 'api')->json(
            'PUT',
            '/api/admin/settings/9999',
            $input
        );
        $this->response->assertNotFound();
    }

    public function test_admin_update_number()
    {
        $setting = Setting::first();

        $input = [
            'group' => 'config',
            'key' => 'number_field',
            'value' => 123,
            'public' => true,
            'enumerable' => true,
            'type' => SettingTypes::NUMBER
        ];

        $this->response = $this->actingAs($this->user, 'api')->json(
            'PUT',
            '/api/admin/settings/' . $setting->id,
            $input
        );

        $this->response->assertOk();

        $this->assertEquals($input['value'], $this->response->getData()->data->data);
    }

    public function test_admin_update_number_invalid()
    {
        $setting = Setting::first();

        $input = [
            'group' => 'config',
            'key' => 'number_field',
            'value' => 'invalid',
            'public' => true,
            'enumerable' => true,
            'type' => SettingTypes::NUMBER
        ];

        $this->response = $this->actingAs($this->user, 'api')->json(
            'PUT',
            '/api/admin/settings/' . $setting->id,
            $input
        )->assertUnprocessable();
    }

    public function test_admin_update_boolean()
    {
        $setting = Setting::first();

        $input = [
            'group' => 'texts',
            'key' => 'boolean_field',
            'value' => true,
            'public' => true,
            'enumerable' => true,
            'type' => SettingTypes::BOOLEAN
        ];

        $this->response = $this->actingAs($this->user, 'api')->json(
            'PUT',
            '/api/admin/settings/' . $setting->id,
            $input
        );

        $this->response->assertOk();

        $this->assertEquals($input['value'], $this->response->getData()->data->data);
    }

    public function test_admin_update_boolean_invalid()
    {
        $setting = Setting::first();

        $input = [
            'group' => 'config',
            'key' => 'boolean_field',
            'value' => 'invalid',
            'public' => true,
            'enumerable' => true,
            'type' => SettingTypes::BOOLEAN
        ];

        $this->response = $this->actingAs($this->user, 'api')->json(
            'PUT',
            '/api/admin/settings/' . $setting->id,
            $input
        )->assertUnprocessable();
    }

    public function test_admin_create()
    {
        $input = [
            'group' => 'config',
            'key' => 'app.env',
            'value' => "app.env",
            'public' => true,
            'enumerable' => true,
            'type' => 'config'
        ];

        $this->response = $this->actingAs($this->user, 'api')->json(
            'POST',
            '/api/admin/settings',
            $input
        );

        $this->response->assertOk();

        $this->assertEquals($input['value'], $this->response->getData()->data->value);
    }

    public function test_admin_create_not_duplicate()
    {
        $input = [
            'group' => 'global',
            'key' => 'logo',
            'value' => "//logo.png",
            'public' => true,
            'enumerable' => true,
            'type' => 'image'
        ];

        $this->response = $this->actingAs($this->user, 'api')->json(
            'POST',
            '/api/admin/settings',
            $input
        );
        $id = $this->response->getData()->data->id;

        $this->response->assertOk();
        $this->assertEquals($input['value'], $this->response->getData()->data->value);

        $this->response = $this->actingAs($this->user, 'api')->json(
            'POST',
            '/api/admin/settings',
            $input
        );

        $this->response->assertOk();
        $this->assertEquals($id, $this->response->getData()->data->id);
        $this->assertEquals($input['value'], $this->response->getData()->data->value);

        $this->assertCount(1, Setting::query()->where('group', 'global')->where('key', 'logo')->get());
        $this->assertDatabaseHas('settings', $input);
    }

    public function test_admin_create_number()
    {
        $input = [
            'group' => 'texts',
            'key' => 'number_field',
            'value' => 123,
            'public' => true,
            'enumerable' => true,
            'type' => SettingTypes::NUMBER,
        ];

        $this->response = $this->actingAs($this->user, 'api')->json(
            'POST',
            '/api/admin/settings',
            $input
        );
        $this->response->assertOk();

        $this->assertEquals($input['value'], $this->response->getData()->data->data);
    }

    public function test_admin_create_number_invalid()
    {
        $input = [
            'group' => 'texts',
            'key' => 'number_field',
            'value' => "invalid value",
            'public' => true,
            'enumerable' => true,
            'type' => SettingTypes::NUMBER,
        ];

        $this->response = $this->actingAs($this->user, 'api')->json(
            'POST',
            '/api/admin/settings',
            $input
        )->assertUnprocessable();
    }

    public function test_admin_create_boolean()
    {
        $input = [
            'group' => 'texts',
            'key' => 'boolean_field',
            'value' => true,
            'public' => true,
            'enumerable' => true,
            'type' => SettingTypes::BOOLEAN,
        ];

        $this->response = $this->actingAs($this->user, 'api')->json(
            'POST',
            '/api/admin/settings',
            $input
        );
        $this->response->assertOk();

        $this->assertEquals($input['value'], $this->response->getData()->data->data);
    }

    public function test_admin_create_boolean_invalid()
    {
        $input = [
            'group' => 'texts',
            'key' => 'boolean_field',
            'value' => "invalid value",
            'public' => true,
            'enumerable' => true,
            'type' => SettingTypes::BOOLEAN,
        ];

        $this->response = $this->actingAs($this->user, 'api')->json(
            'POST',
            '/api/admin/settings',
            $input
        )->assertUnprocessable();
    }

    public function test_admin_delete()
    {
        $setting = Setting::first();

        $this->response = $this->actingAs($this->user, 'api')->json(
            'DELETE',
            '/api/admin/settings/' . $setting->id,
        );

        $this->response->assertOk();

        $this->response = $this->actingAs($this->user, 'api')->json(
            'DELETE',
            '/api/admin/settings/' . $setting->id,
        );

        $this->response->assertNotFound();
    }

    public function test_admin_create_array()
    {
        $input = [
            'group' => 'texts',
            'key' => 'boolean_field',
            'value' => [
                'test1',
                'test2',
            ],
            'public' => true,
            'enumerable' => true,
            'type' => SettingTypes::ARRAY,
        ];

        $this->response = $this->actingAs($this->user, 'api')->json(
            'POST',
            '/api/admin/settings',
            $input
        );
        $this->response->assertOk();

        $this->assertEquals($input['value'], $this->response->getData()->data->data);
    }

    public function test_admin_update_array()
    {
        $setting = Setting::first();

        $input = [
            'group' => 'texts',
            'key' => 'boolean_field',
            'value' => [
                'test1',
            ],
            'public' => true,
            'enumerable' => true,
            'type' => SettingTypes::ARRAY
        ];

        $this->response = $this->actingAs($this->user, 'api')->json(
            'PUT',
            '/api/admin/settings/' . $setting->id,
            $input
        );

        $this->response->assertOk();

        $this->assertEquals($input['value'], $this->response->getData()->data->data);
    }
}
