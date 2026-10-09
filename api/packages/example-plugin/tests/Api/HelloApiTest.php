<?php

namespace Ulams\ExamplePlugin\Tests\Api;

use Ulams\ExamplePlugin\Tests\TestCase;

class HelloApiTest extends TestCase
{
    public function testReturnsTheConfiguredGreeting(): void
    {
        $this->getJson('/api/example-plugin/hello')
            ->assertOk()
            ->assertJsonPath('data.greeting', 'Hello from the tests');

        $this->getJson('/api/example-plugin/hello?name=Ada')
            ->assertOk()
            ->assertJsonPath('data.greeting', 'Hello from the tests, Ada');
    }

    public function testTheGreetingIsAPublicSetting(): void
    {
        $this->getJson('/api/config')
            ->assertOk()
            ->assertJsonPath('data.ulams_example_plugin.greeting', 'Hello from the tests');
    }

    public function testAnAdminChangesTheGreetingThroughTheSettingsApi(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')->postJson('/api/admin/config', [
            'config' => [['key' => 'ulams_example_plugin.greeting', 'value' => 'Welcome aboard']],
        ])->assertOk();

        $this->getJson('/api/example-plugin/hello')->assertJsonPath('data.greeting', 'Welcome aboard');
    }

    public function testTheSettingIsValidated(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')->postJson('/api/admin/config', [
            'config' => [['key' => 'ulams_example_plugin.greeting', 'value' => str_repeat('x', 201)]],
        ])->assertUnprocessable();
    }
}
