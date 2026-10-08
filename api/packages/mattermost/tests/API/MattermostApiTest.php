<?php

namespace EscolaLms\Mattermost\Tests\API;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Mattermost\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use GuzzleHttp\Psr7\Response;
use EscolaLms\Mattermost\Services\Contracts\MattermostServiceContract;
use EscolaLms\Core\Enums\UserRole;

class MattermostApiTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions;

    private MattermostServiceContract $service;
    private $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole(UserRole::STUDENT);

        $this->service = $this->app->make(MattermostServiceContract::class);
        $this->mock->reset();
    }

    public function testAnonymous()
    {
        $this->getJson('/api/mattermost/me')->assertUnauthorized();
        $this->getJson('/api/mattermost/reset_password')->assertUnauthorized();
        $this->getJson('/api/mattermost/generate_credentials')->assertUnauthorized();
    }

    public function testMe()
    {
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123, "name" => "name"])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode([["id" => 123, "name" => "name"]])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode([["id" => 123, "name" => "name"]])));

        $result = $this->actingAs($this->user, 'api')->getJson('/api/mattermost/me');

        $result->assertOk();

        $json =  [
            "server" => "localhost",
            "teams" => [
                [
                    "id" => 123,
                    "name" => "name",
                    "channels" => [[
                        "id" => 123,
                        "name" => "name",
                        "url" => "https://localhost/name/name"
                    ]]
                ]
            ]

        ];

        $result->assertJsonFragment($json);
    }

    public function testGenerateCredentials()
    {
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123, "name" => "name"])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["status" => "ok"])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode([["id" => 123, "name" => "name"]])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode([["id" => 123, "name" => "name"]])));

        $result = $this->actingAs($this->user, 'api')->getJson('/api/mattermost/generate_credentials');

        $result->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'status' => ['status'],
                'user' => ['id'],
                'password'
            ],

        ]);

        $result->assertOk();
    }

    public function testResetPassword()
    {
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123, "name" => "name"])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["status" => "ok"])));

        $result = $this->actingAs($this->user, 'api')->getJson('/api/mattermost/reset_password');

        $result->assertOk();
    }
}
