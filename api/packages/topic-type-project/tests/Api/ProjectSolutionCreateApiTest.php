<?php

namespace Ulams\TopicTypeProject\Tests\Api;

use Ulams\Core\Models\User;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeProject\Database\Seeders\TopicTypeProjectPermissionSeeder;
use Ulams\TopicTypeProject\Events\ProjectSolutionCreatedEvent;
use Ulams\TopicTypeProject\Models\Project;
use Ulams\TopicTypeProject\Models\ProjectSolution;
use Ulams\TopicTypeProject\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectSolutionCreateApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();

        $this->seed(TopicTypeProjectPermissionSeeder::class);

        $this->course = Course::factory()->state(['status' => CourseStatusEnum::PUBLISHED])->create();
        $this->topic = Topic::factory()
            ->for(Lesson::factory()->state(['course_id' => $this->course->getKey()]))
            ->create();

        $this->userIds = User::factory()->count(2)->create()->pluck('id');
        $project = Project::factory()->state(['notify_users' => $this->userIds->toArray()])->create();
        $this->topic->topicable()->associate($project)->save();

        Event::fake();
    }

    public function testCreateProjectSolutionUnauthorized(): void
    {
        $this->postJson('api/topic-project-solutions', [
            'topic_id' => $this->topic->getKey(),
            'file' => UploadedFile::fake(),
        ])
            ->assertUnauthorized();

        Event::assertNotDispatched(ProjectSolutionCreatedEvent::class);
    }

    public function testCreateProjectSolutionForbidden(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->postJson('api/topic-project-solutions', [
                'topic_id' => $this->topic->getKey(),
                'file' => UploadedFile::fake(),
            ])
            ->assertForbidden();

        Event::assertNotDispatched(ProjectSolutionCreatedEvent::class);
    }

    public function testCreateProjectSolution(): void
    {
        $student = $this->makeStudent();
        $this->course->users()->sync($student);

        $response = $this->actingAs($student, 'api')
            ->postJson('api/topic-project-solutions', [
                'topic_id' => $this->topic->getKey(),
                'file' => UploadedFile::fake()->create('solution.zip'),
            ]);

        $response
            ->assertCreated()
            ->assertJsonFragment([
                'file_name' => 'solution.zip',
            ]);

        $this->assertTrue(Str::contains($response->json('data.file_url'), 'solution.zip'));


        /** @var ProjectSolution $solution */
        $solution = ProjectSolution::query()->latest()->first();

        $this->assertEquals($this->topic->getKey(), $solution->topic_id);
        $this->assertEquals($student->getKey(), $solution->user_id);
        Storage::assertExists($solution->path);

        Event::assertDispatchedTimes(ProjectSolutionCreatedEvent::class, 2);
        Event::assertDispatched(ProjectSolutionCreatedEvent::class, function (ProjectSolutionCreatedEvent $event) {
            return $this->userIds->contains($event->getUser()->getKey());
        });
    }
}
