<?php

namespace Ulams\Mattermost\Tests\Services;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Mattermost\Enum\MattermostRoleEnum;
use Ulams\Mattermost\Tests\TestCase;
use Ulams\Webinar\Services\Contracts\WebinarServiceContract;
use Ulams\Webinar\Tests\Mocks\YTLiveDtoMock;
use Ulams\Youtube\Services\Contracts\YoutubeServiceContract;
use GuzzleHttp\Psr7\Response;
use Ulams\Mattermost\Http\Controllers\MattermostController;
use Ulams\Mattermost\Services\Contracts\MattermostServiceContract;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery\MockInterface;

class ServiceTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions;

    private MattermostServiceContract $service;
    private $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = $this->makeStudent();
        $this->service = $this->app->make(MattermostServiceContract::class);
    }

    public function testAddUser()
    {
        $this->mock->reset();
        $this->mock->append(new Response(200, ['Token' => 'Token'], 'Hello, World'));
        if (class_exists(\Ulams\Webinar\UlamsWebinarServiceProvider::class)) {
            $this->mock(YoutubeServiceContract::class, function (MockInterface $mock) {
                $mock->shouldReceive('generateYTStream')->zeroOrMoreTimes()->andReturn(new YTLiveDtoMock());
            });
            $this->mock(WebinarServiceContract::class, function (MockInterface $mock) {
                $mock->shouldReceive('hasYT')->zeroOrMoreTimes()->andReturn(true);
            });
        }
        $this->assertTrue($this->service->addUser($this->user));
    }

    public function testAddUserToTeam()
    {
        $this->mock->reset();
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], ""));

        $this->assertTrue($this->service->addUserToTeam($this->user, "Courses"));
    }

    public function testAddUserToChannel()
    {
        $this->mock->reset();
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], ""));
        $this->mock->append(new Response(200, ['Token' => 'Token'], ""));
        $this->mock->append(new Response(200, ['Token' => 'Token'], ""));
        $this->mock->append(new Response(200, ['Token' => 'Token'], ""));

        $this->assertTrue($this->service->addUserToChannel($this->user, "Courses"));
    }

    public function testGetOrCreateTeam()
    {
        $this->mock->reset();
        $response = new Response(200, ['Token' => 'Token'], json_encode(["id" => 123]));
        $this->mock->append($response);

        $this->assertEquals($this->service->getOrCreateTeam("Team name"),  $response);
    }

    public function testGetOrCreateChannel()
    {
        $this->mock->reset();
        $response = new Response(200, ['Token' => 'Token'], json_encode(["id" => 123]));
        $this->mock->append($response);
        $this->mock->append($response);

        $this->assertEquals($this->service->getOrCreateChannel("Team name", "channel name"),  $response);
    }

    public function testGetOrCreateUser()
    {
        $this->mock->reset();
        $response = new Response(200, ['Token' => 'Token'], json_encode(["id" => 123]));
        $this->mock->append($response);

        $this->assertEquals($this->service->getOrCreateUser($this->user),  $response);
    }

    public function testSendMessage()
    {
        $this->mock->reset();
        $response = new Response(200, ['Token' => 'Token'], json_encode(["id" => 123]));
        $this->mock->append($response);
        $this->mock->append($response);

        $this->assertTrue($this->service->sendMessage("hello world", "Town Square"));
    }

    public function testGenerateUserCredentials()
    {
        $this->mock->reset();
        $object = ["id" => 123];
        $response = new Response(200, ['Token' => 'Token'], json_encode($object));
        $this->mock->append($response);
        $this->mock->append($response);

        $response = $this->service->generateUserCredentials($this->user);

        $this->assertEquals((array) $response['status'], $object);
        $this->assertEquals((array) $response['user'], $object);
        $this->assertNotNull($response['password']);
    }

    public function testBlockUser()
    {
        $this->mock->reset();
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["status" => 'ok'])));
        $this->assertTrue($this->service->blockUser($this->user));

        $this->mock->append(new Response(404, ['Token' => 'Token']));
        $this->assertFalse($this->service->blockUser($this->user));
    }

    public function testDeleteUser()
    {
        $this->mock->reset();
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["status" => 'ok'])));
        $this->assertTrue($this->service->deleteUser($this->user));

        $this->mock->append(new Response(404, ['Token' => 'Token']));
        $this->assertFalse($this->service->blockUser($this->user));
    }

    public function testAddTutorToChanel(): void
    {
        $this->mock->reset();
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], ""));
        $this->mock->append(new Response(200, ['Token' => 'Token'], ""));
        $this->mock->append(new Response(200, ['Token' => 'Token'], ""));
        $this->mock->append(new Response(200, ['Token' => 'Token'], ""));

        $this->assertTrue($this->service->addUserToChannel($this->user, 'Channel name', 'Courses', MattermostRoleEnum::CHANNEL_ADMIN));
    }

    public function testRemoveUserFromChannel(): void
    {
        $this->mock->reset();
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["status" => 'ok'])));

        $this->assertTrue($this->service->removeUserFromChannel($this->user, 'Channel name'));
    }

    public function testBuildingTheServiceDoesNoHttp(): void
    {
        // A fresh application has no Mattermost connection yet.
        $this->refreshApplication();
        $this->container = [];

        $service = $this->app->make(MattermostServiceContract::class);
        $this->app->make(MattermostController::class);

        $this->assertCount(0, $this->container, 'Building the service must not call Mattermost.');

        $this->mock->reset();
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(['id' => 'admin'])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(['id' => 123])));

        $this->assertTrue($service->addUser($this->user));
        $this->assertCount(2, $this->container);
        $this->assertStringEndsWith('/users/login', $this->container[0]['request']->getUri()->getPath());
    }
}
