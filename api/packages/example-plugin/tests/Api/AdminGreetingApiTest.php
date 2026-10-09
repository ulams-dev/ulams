<?php

namespace Ulams\ExamplePlugin\Tests\Api;

use Illuminate\Support\Facades\Event;
use Ulams\ExamplePlugin\Events\GreetingSent;
use Ulams\ExamplePlugin\Tests\TestCase;

class AdminGreetingApiTest extends TestCase
{
    public function testAnAdminSendsAGreetingAndTheEventIsDispatched(): void
    {
        Event::fake([GreetingSent::class]);
        $admin = $this->makeAdmin();
        $student = $this->makeStudent(['first_name' => 'Ada']);

        $this->actingAs($admin, 'api')
            ->postJson('/api/admin/example-plugin/greetings', ['user_id' => $student->getKey()])
            ->assertOk()
            ->assertJsonPath('data.greeting', 'Hello from the tests, Ada')
            ->assertJsonPath('data.user_id', $student->getKey());

        Event::assertDispatched(
            GreetingSent::class,
            fn (GreetingSent $event) => $event->getUser()->getKey() === $student->getKey()
                && $event->getGreeting() === 'Hello from the tests, Ada'
        );
    }

    public function testRequiresAuthentication(): void
    {
        $this->postJson('/api/admin/example-plugin/greetings', ['user_id' => 1])->assertUnauthorized();
    }

    public function testRequiresThePermission(): void
    {
        Event::fake([GreetingSent::class]);
        $student = $this->makeStudent();

        $this->actingAs($student, 'api')
            ->postJson('/api/admin/example-plugin/greetings', ['user_id' => $student->getKey()])
            ->assertForbidden();

        Event::assertNotDispatched(GreetingSent::class);
    }

    public function testValidatesTheRecipient(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->postJson('/api/admin/example-plugin/greetings', ['user_id' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }
}
