<?php

namespace EscolaLms\BulkNotifications\Tests\Api;

use EscolaLms\BulkNotifications\Database\Seeders\BulkNotificationPermissionSeeder;
use EscolaLms\BulkNotifications\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;
use Illuminate\Foundation\Testing\WithFaker;

class DeviceTokenApiTest extends TestCase
{
    use CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BulkNotificationPermissionSeeder::class);
    }

    public function testCreateDeviceToken(): void
    {
        $user = $this->makeStudent();
        $deviceToken = $this->faker->uuid;

        $this->actingAs($user, 'api')
            ->postJson('api/notifications/tokens', ['token' => $deviceToken])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->getKey(),
            'token' => $deviceToken,
        ]);
    }

    public function testCreateDeviceTokenNotDuplicate(): void
    {
        $user = $this->makeStudent();
        $deviceToken = $this->faker->uuid;

        $this->actingAs($user, 'api')
            ->postJson('api/notifications/tokens', ['token' => $deviceToken])
            ->assertOk();

        $this->actingAs($user, 'api')
            ->postJson('api/notifications/tokens', ['token' => $deviceToken])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->getKey(),
            'token' => $deviceToken,
        ]);
        $this->assertDatabaseCount('device_tokens', 1);
    }

    public function testCreateDeviceTokenUpdateUser(): void
    {
        $user1 = $this->makeStudent();
        $user2 = $this->makeStudent();
        $deviceToken = $this->faker->uuid;

        $this->actingAs($user1, 'api')
            ->postJson('api/notifications/tokens', ['token' => $deviceToken])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user1->getKey(),
            'token' => $deviceToken,
        ]);

        $this->actingAs($user2, 'api')
            ->postJson('api/notifications/tokens', ['token' => $deviceToken])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user2->getKey(),
            'token' => $deviceToken,
        ]);
        $this->assertDatabaseMissing('device_tokens', [
            'user_id' => $user1->getKey(),
            'token' => $deviceToken,
        ]);
        $this->assertDatabaseCount('device_tokens', 1);
    }

    public function testCreateDeviceTokenInvalidData(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->postJson('api/notifications/tokens', ['token' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);

        $this->actingAs($this->makeStudent(), 'api')
            ->postJson('api/notifications/tokens', ['token' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    public function testCreateDeviceTokenForbidden(): void
    {
        $user = config('auth.providers.users.model')::factory()->create();

        $this->actingAs($user, 'api')
            ->postJson('api/notifications/tokens', ['token' => $this->faker->uuid])
            ->assertForbidden();
    }

    public function testCreateDeviceTokenUnauthorized(): void
    {
        $this->postJson('api/notifications/tokens', ['token' => $this->faker->uuid])
            ->assertUnauthorized();
    }
}
