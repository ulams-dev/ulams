<?php

namespace Ulams\TopicTypeProject\Tests\Api;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeProject\Models\Project;
use Ulams\TopicTypeProject\Tests\TestCase;

class TopicTypeProjectClientApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesPermissionSeeder::class);

        $this->user = $this->makeAdmin();
        $this->topic = Topic::factory()
            ->for(Lesson::factory()
                ->for(Course::factory()))
            ->create();
    }

    public function testGetProjectTopic(): void
    {
        $project = Project::factory()->create();
        $this->topic->topicable()->associate($project)->save();

        $this->actingAs($this->user, 'api')
            ->getJson('/api/admin/topics/' . $this->topic->getKey())
            ->assertOk()
            ->assertJsonFragment([
                'topicable_type' => Project::class,
            ]);
    }

    public function testGetProjectTopicReturnsCountsToGrade(): void
    {
        // Topicable content is only serialized for an active lesson (courses TopicResource).
        $topic = Topic::factory()
            ->for(Lesson::factory()->state(['active' => true])->for(Course::factory()))
            ->create();
        $project = Project::factory()->create(['counts_to_grade' => true]);
        $topic->topicable()->associate($project)->save();

        $this->actingAs($this->user, 'api')
            ->getJson('/api/admin/topics/' . $topic->getKey())
            ->assertOk()
            ->assertJsonFragment([
                'counts_to_grade' => true,
            ]);
    }
}
