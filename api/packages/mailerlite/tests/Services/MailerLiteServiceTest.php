<?php

namespace EscolaLms\MailerLite\Tests\Services;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\MailerLite\Services\MailerLiteService;
use EscolaLms\MailerLite\Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class MailerLiteServiceTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions, WithoutMiddleware;

    private $mock;

    public function setUp(): void
    {
        parent::setUp();
        $this->mock = new MockHandler();
        $handlerStack = HandlerStack::create($this->mock);
        $guzzle = new Client(['handler' => $handlerStack]);
        $guzzleClient = new \Http\Adapter\Guzzle7\Client($guzzle);
        $this->service = new MailerLiteService($guzzleClient);
    }

    public function testGetGroup(): void
    {
        $this->mock->append(new Response(200, ['X-MailerLite-ApiKey' => '123'], json_encode(['id' => 123, 'name' => 'testGroup'])));

        $result = $this->service->getOrCreateGroup('testGroup');
        $this->assertEquals('testGroup', $result->name);
    }

    public function testCreateGroup(): void
    {
        $this->mock->append(new Response(200, ['X-MailerLite-ApiKey' => '123'], json_encode([])));
        $this->mock->append(new Response(200, ['X-MailerLite-ApiKey' => '123'], json_encode(['id' => 123, 'name' => 'testNewGroup'])));

        $result = $this->service->getOrCreateGroup('testNewGroup');
        $this->assertEquals('testNewGroup', $result->name);
    }

    public function testAddSubscriberToGroup(): void
    {
        $student = $this->makeStudent([
            'email' => 'test@example.com',
        ]);

        $this->mock->append(new Response(200, ['X-MailerLite-ApiKey' => '123'], json_encode(['id' => 123, 'name' => 'testGroup'])));
        $this->mock->append(new Response(200, ['X-MailerLite-ApiKey' => '123'], json_encode(['id' => 123, 'email' => 'test@example.com'])));

        $this->assertTrue($this->service->addSubscriberToGroup('testGroup', $student));
    }

    public function testRemoveSubscriberFromGroup(): void
    {
        $student = $this->makeStudent([
            'email' => 'test@example.com',
        ]);

        $this->mock->append(new Response(200, ['X-MailerLite-ApiKey' => '123'], json_encode(['id' => 123, 'name' => 'testGroup'])));
        $this->mock->append(new Response(200, ['X-MailerLite-ApiKey' => '123'], json_encode(['id' => 123, 'email' => 'test@example.com'])));
        $this->mock->append(new Response(200, ['X-MailerLite-ApiKey' => '123']));

        $this->assertTrue($this->service->removeSubscriberFromGroup('testGroup', $student));
    }

    public function testDeleteExistingSubscriber(): void
    {
        $student = $this->makeStudent([
            'email' => 'test@example.com',
        ]);

        $this->mock->append(new Response(200, ['X-MailerLite-ApiKey' => '123'], json_encode(['id' => 123, 'email' => 'test@example.com'])));
        $this->mock->append(new Response(200, ['X-MailerLite-ApiKey' => '123']));

        $this->assertTrue($this->service->deleteSubscriber($student));
    }

    public function testDeleteNonexistentSubscriber(): void
    {
        $student = $this->makeStudent([
            'email' => 'test@example.com',
        ]);

        $this->mock->append(new Response(200, ['X-MailerLite-ApiKey' => '123']));

        $this->assertFalse($this->service->deleteSubscriber($student));
    }
}
