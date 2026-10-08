<?php

namespace EscolaLms\Templates\Tests\Api;

use EscolaLms\Auth\Database\Seeders\AuthPermissionSeeder;
use EscolaLms\Auth\Models\User;
use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Templates\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class UserTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\EscolaLms\Auth\EscolaLmsAuthServiceProvider::class)) {
            $this->markTestSkipped('Auth package not installed');
        }

        $this->seed(AuthPermissionSeeder::class);
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    public function testUserUpdate()
    {
        $user = $this->makeStudent();
        $admin = $this->makeAdmin();

        $this->response = $this->actingAs($admin)->json('PATCH', '/api/admin/users/' . $user->getKey(), [
            'notification_channels' => [
                "EscolaLms\\TemplatesEmail\\Core\\EmailChannel",
                "EscolaLms\\TemplatesSms\\Core\\SmsChannel"
            ]
        ]);

        $this->response
            ->assertOk()
            ->assertJsonFragment([
                'notification_channels' => [
                    "EscolaLms\\TemplatesEmail\\Core\\EmailChannel",
                    "EscolaLms\\TemplatesSms\\Core\\SmsChannel"
                ]
            ]);

        $this->response = $this->actingAs($admin)
            ->json('PATCH', '/api/admin/users/' . $user->getKey(), [
                'notification_channels' => [
                    'EscolaLms\\TemplatesSms\\Core\\SmsChannel'
                ]
            ]);

        $this->response
            ->assertOk()
            ->assertJsonFragment([
                'notification_channels' => ['EscolaLms\\TemplatesSms\\Core\\SmsChannel']
            ])
            ->assertJsonMissing(['notification_channels' => ['EscolaLms\\TemplatesEmail\\Core\\EmailChannel']]);
    }

    public function testGetUser(): void
    {
        $user = $this->makeStudent();
        $admin = $this->makeAdmin();

        $this->response = $this->actingAs($admin)->json('PATCH', '/api/admin/users/' . $user->getKey(), [
            'notification_channels' => [
                "EscolaLms\\TemplatesEmail\\Core\\EmailChannel",
                "EscolaLms\\TemplatesSms\\Core\\SmsChannel"
            ]
        ]);

        $this->response = $this->actingAs($admin)->json('GET', '/api/admin/users/' . $user->getKey());

        $this->response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $user->getKey(),
                'email' => $user->email,
                'first_name' => $user->first_name,
                'notification_channels' => [
                    "EscolaLms\\TemplatesEmail\\Core\\EmailChannel",
                    "EscolaLms\\TemplatesSms\\Core\\SmsChannel"
                ]
            ]);
    }
}
