<?php

namespace EscolaLms\TopicTypeProject\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Database\Seeders\CoursesPermissionSeeder;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypeProject\Models\Project;
use EscolaLms\TopicTypeProject\Tests\TestCase;
use EscolaLms\TopicTypes\Events\TopicTypeChanged;
use Illuminate\Support\Facades\Event;

class TopicTypeProjectUpdateApiTest extends TestCase
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

    public function testUpdateTopicProject(): void
    {
        Event::fake([TopicTypeChanged::class]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/admin/topics/' . $this->topic->getKey(), [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => Project::class,
                'value' => 'lorem ipsum',
                'notify_users' => [1, 3, 5]
            ])
            ->assertOk();

        $data = $response->getData()->data;
        $project = Project::find($data->topicable->id);

        $this->assertEquals('lorem ipsum', $project->value);
        $this->assertEquals([1, 3, 5], $project->notify_users);

        Event::assertDispatched(TopicTypeChanged::class, function ($event) {
            return $event->getUser() === $this->user && $event->getTopicContent();
        });
    }

    public function testUpdateTopicProjectAcceptsCountsToGrade(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/admin/topics/' . $this->topic->getKey(), [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => Project::class,
                'value' => 'lorem ipsum',
                'counts_to_grade' => true,
            ])
            ->assertOk()
            ->assertJsonFragment(['counts_to_grade' => true]);

        $project = Project::find($response->getData()->data->topicable->id);

        $this->assertTrue($project->counts_to_grade);
    }

    public function testUpdateTopicProjectAcceptsWeight(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/admin/topics/' . $this->topic->getKey(), [
                'title' => 'Hello World',
                'lesson_id' => $this->topic->lesson_id,
                'topicable_type' => Project::class,
                'value' => 'lorem ipsum',
                'weight' => 5,
            ])
            ->assertOk()
            ->assertJsonFragment(['weight' => 5]);

        $project = Project::find($response->getData()->data->topicable->id);

        $this->assertEquals(5, $project->weight);
    }
}
