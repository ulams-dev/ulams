<?php

namespace Ulams\Video\Tests\Api;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Video\Database\Seeders\VideoPermissionSeeder;
use Ulams\Video\Models\Video;
use Ulams\Video\Tests\TestCase;
use Ulams\Video\Tests\VideoTesting;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class VideoControllerTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers, VideoTesting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VideoPermissionSeeder::class);
    }

    public function videoDataProvider(): void
    {
        Course::factory()
            ->has(Lesson::factory()
                ->has(Topic::factory()
                    ->count(25)
                    ->state(fn() => [
                        'json' => [
                            'ffmpeg' => [
                                'state' => 'error',
                                'message' => 'Something went wrong!'
                            ]
                        ],
                        'topicable_type' => \Ulams\TopicTypes\Models\TopicContent\Video::class,
                        'topicable_id' => Video::factory()->create()->getKey()
                    ])
                )
            )
            ->create();
        Course::factory()
            ->has(Lesson::factory()
                ->has(Topic::factory()
                    ->count(10)
                    ->state(fn() => [
                        'json' => [
                            'ffmpeg' => [
                                'state' => 'finished',
                                'path' => '/courses/123/topic/123/video/video.m3u8'
                            ]
                        ],
                        'topicable_type' => \Ulams\TopicTypes\Models\TopicContent\Video::class,
                        'topicable_id' => Video::factory()->create()->getKey()
                    ])
                )
            )
            ->create();
        Course::factory()
            ->has(Lesson::factory()
                ->has(Topic::factory()
                    ->count(5)
                    ->state(fn() => [
                        'json' => [
                            'ffmpeg' => [
                                'state' => 'coding',
                                'percentage' => random_int(0, 100)
                            ]
                        ],
                        'topicable_type' => \Ulams\TopicTypes\Models\TopicContent\Video::class,
                        'topicable_id' => Video::factory()->create()->getKey()
                    ])
                )
            )
            ->create();

    }

    public function testVideoProcessStateFilter(): void
    {
        $this->videoDataProvider();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('/api/admin/video/states')
            ->assertJsonCount(40, 'data');

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('/api/admin/video/states?state=error')
            ->assertJsonCount(25, 'data');

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('/api/admin/video/states?state=finished')
            ->assertJsonCount(10, 'data');

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('/api/admin/video/states?state=coding')
            ->assertJsonCount(5, 'data');

    }

    public function testVideoProcessStateUnauthorized(): void
    {
        $this->getJson('/api/admin/video/states')
            ->assertUnauthorized();
    }

    public function testVideoProcessStateForbidden(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->getJson('/api/admin/video/states')
            ->assertForbidden();
    }
}
