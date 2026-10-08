<?php

namespace EscolaLms\Mattermost\Tests\API;

use EscolaLms\Auth\Models\User;
use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Mattermost\Enum\PackageStatusEnum;
use EscolaLms\Mattermost\Services\Contracts\MattermostServiceContract;
use EscolaLms\Mattermost\Tests\TestCase;
use EscolaLms\Webinar\Database\Seeders\WebinarsPermissionSeeder;
use EscolaLms\Webinar\Models\Webinar;
use EscolaLms\Webinar\Tests\Mocks\YTLiveDtoMock;
use EscolaLms\Youtube\Services\Contracts\YoutubeServiceContract;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;

class WebinarApiTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\EscolaLms\Webinar\EscolaLmsWebinarServiceProvider::class)) {
            $this->markTestSkipped('Webinar package not installed');
        }

        config([
            'services.youtube.client_id' => 'test_client_id',
            'services.youtube.client_secret' => 'test_secret',
            'services.youtube.api_key' => 'test_api_key',
            'services.youtube.refresh_token' => 'test_refresh_token',
            'services.youtube.redirect_url' => 'redirect_url',
        ]);

        $this->seed(WebinarsPermissionSeeder::class);
        Config::set('escola_settings.use_database', true);
        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
        $this->mock->reset();
    }

    protected function tearDown(): void
    {
        \EscolaLms\Settings\Models\Config::truncate();
        User::query()->delete();
        Webinar::query()->delete();
    }

    public function testAddWebinarTrainerToChannel(): void
    {
        $webinar = Webinar::factory()->make([
            'active_from' => now()->format('Y-m-d H:i'),
            'active_to' => now()->modify('+1 hour')->format('Y-m-d H:i'),
        ])->toArray();

        $trainer = $this->makeInstructor();

        $this->setPackageStatus(PackageStatusEnum::DISABLED);

        $this->mock(YoutubeServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->zeroOrMoreTimes()->andReturn(true);
            $mock->shouldReceive('generateYTStream')->once()->andReturn(new YTLiveDtoMock());
            $mock->shouldReceive('getYtLiveStream')->zeroOrMoreTimes()->andReturn(collect());
        });
        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->never();
        });
        $this->response = $this->actingAs($this->user, 'api')->postJson('/api/admin/webinars',
            array_merge($webinar, ['trainers' => [$trainer->getKey()]])
        )->assertStatus(201);

        $this->setPackageStatus(PackageStatusEnum::ENABLED);

        $webinar = Webinar::factory()->make([
            'active_from' => now()->format('Y-m-d H:i'),
            'active_to' => now()->modify('+1 hour')->format('Y-m-d H:i'),
        ])->toArray();
        $trainer = $this->makeInstructor();

        $this->mock(YoutubeServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->zeroOrMoreTimes()->andReturn(true);
            $mock->shouldReceive('generateYTStream')->zeroOrMoreTimes()->andReturn(new YTLiveDtoMock());
            $mock->shouldReceive('getYtLiveStream')->zeroOrMoreTimes()->andReturn(collect());
        });

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->once()->andReturn(true);
        });
        $this->response = $this->actingAs($this->user, 'api')->postJson('/api/admin/webinars',
            array_merge($webinar, ['trainers' => [$trainer->getKey()]])
        )->assertStatus(201);
    }

    public function testRemoveTrainerFromWebinar(): void
    {
        $trainer = $this->makeStudent();
        $webinar = Webinar::factory()->create([
            'active_from' => now()->format('Y-m-d H:i'),
            'active_to' => now()->modify('+1 hour')->format('Y-m-d H:i'),
        ]);
        $webinar->trainers()->sync([$trainer->getKey()]);

        $this->setPackageStatus(PackageStatusEnum::DISABLED);

        $this->mock(YoutubeServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->zeroOrMoreTimes()->andReturn(true);
            $mock->shouldReceive('updateYTStream')->once()->andReturn(new YTLiveDtoMock());
            $mock->shouldReceive('getYtLiveStream')->zeroOrMoreTimes()->andReturn(collect(['s']));
        });

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('removeUserFromChannel')->never();
        });

        $this->response = $this->actingAs($this->user, 'api')->postJson('/api/admin/webinars/' . $webinar->getKey(), [
            'trainers' => []
        ])->assertOk();

        $this->setPackageStatus(PackageStatusEnum::ENABLED);

        $this->mock(YoutubeServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->zeroOrMoreTimes()->andReturn(true);
            $mock->shouldReceive('updateYTStream')->once()->andReturn(new YTLiveDtoMock());
            $mock->shouldReceive('getYtLiveStream')->zeroOrMoreTimes()->andReturn(collect(['s']));
        });

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('removeUserFromChannel')->once()->andReturn(true);
        });

        $webinar->trainers()->sync([$trainer->getKey()]);

        $this->response = $this->actingAs($this->user, 'api')->postJson('/api/admin/webinars/' . $webinar->getKey(), [
            'trainers' => []
        ])->assertOk();
    }
}
