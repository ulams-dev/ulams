<?php

namespace Ulams\Mattermost\Tests\API;

use Ulams\Auth\Database\Seeders\AuthPermissionSeeder;
use Ulams\Auth\Models\User;
use Ulams\Core\Tests\ApiTestTrait;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Mattermost\Enum\PackageStatusEnum;
use Ulams\Mattermost\Services\Contracts\MattermostServiceContract;
use Ulams\Mattermost\Tests\TestCase;
use Ulams\Settings\Models\Config;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Mockery\MockInterface;

class AuthApiTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthPermissionSeeder::class);
        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
        $this->mock->reset();
    }

    protected function tearDown(): void
    {
        \Ulams\Settings\Models\Config::truncate();
        User::query()->delete();
    }

    public function testAccountConfirmedTemplateEventListenerWithPackageStatusSetting(): void
    {
        $this->setPackageStatus(PackageStatusEnum::DISABLED);

        $student1 = $this->makeStudent([
            'email_verified_at' => null
        ]);

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUser')->never();
        });

        $this->response = $this->actingAs($this->user, 'api')->patchJson('/api/admin/users/' . $student1->getKey(), [
            'email_verified' => true,
        ])->assertOk();

        $this->setPackageStatus(PackageStatusEnum::ENABLED);

        $student2 = $this->makeStudent([
            'email_verified_at' => null
        ]);

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUser')->once()->andReturn(true);
        });

        $this->response = $this->actingAs($this->user, 'api')->patchJson('/api/admin/users/' . $student2->getKey(), [
            'email_verified' => true,
        ])->assertOk();
    }
}
