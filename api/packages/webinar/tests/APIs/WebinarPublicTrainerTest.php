<?php

namespace Ulams\Webinar\Tests\APIs;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ulams\Webinar\Database\Seeders\WebinarsPermissionSeeder;
use Ulams\Webinar\Enum\WebinarStatusEnum;
use Ulams\Webinar\Models\Webinar;
use Ulams\Youtube\Services\Contracts\YoutubeServiceContract;
use Ulams\Webinar\Tests\TestCase;

/**
 * Public webinar trainers carry no e-mail address; admin routes keep it.
 */
class WebinarPublicTrainerTest extends TestCase
{
    use DatabaseTransactions;

    private const EMAIL = 'private-trainer@example.com';

    private Webinar $webinar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WebinarsPermissionSeeder::class);
        $youtube = $this->mock(YoutubeServiceContract::class);
        $youtube->shouldReceive('isConfigured')->zeroOrMoreTimes()->andReturn(true);
        $youtube->shouldReceive('getYtLiveStream')->zeroOrMoreTimes()->andReturn(collect());

        $this->user = config('auth.providers.users.model')::factory()->create(['email' => self::EMAIL]);
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
        $this->webinar = Webinar::factory()->create([
            'status' => WebinarStatusEnum::PUBLISHED,
            'active_from' => now()->addDay(),
            'active_to' => now()->addDays(2),
            'duration' => 60,
        ]);
        $this->webinar->trainers()->sync($this->user);
    }

    public function testPublicTrainersHaveNoEmail(): void
    {
        $list = $this->getJson('/api/webinars?per_page=100')->assertOk();
        $item = collect($list->json('data'))->firstWhere('id', $this->webinar->getKey());
        $this->assertNotNull($item);
        $this->assertSame($this->user->getKey(), $item['trainers'][0]['id']);
        $this->assertArrayNotHasKey('email', $item['trainers'][0]);
        $this->assertStringNotContainsString(self::EMAIL, $list->getContent());

        $this->getJson('/api/webinars/' . $this->webinar->getKey())->assertOk()
            ->assertJsonMissingPath('data.trainers.0.email');
    }

    public function testAdminStillSeesTheTrainerEmail(): void
    {
        $this->actingAs($this->user, 'api')->getJson('/api/admin/webinars/' . $this->webinar->getKey())->assertOk()
            ->assertJsonPath('data.trainers.0.email', self::EMAIL);
    }
}
