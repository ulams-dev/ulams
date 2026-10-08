<?php

namespace EscolaLms\TemplatesEmail\Tests\Api;

use EscolaLms\Core\Models\User;
use EscolaLms\Courses\Enum\CourseStatusEnum;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Templates\Listeners\TemplateEventListener;
use EscolaLms\TemplatesEmail\Core\EmailMailable;
use EscolaLms\TemplatesEmail\Tests\TestCase;
use EscolaLms\TopicTypeProject\Database\Seeders\TopicTypeProjectPermissionSeeder;
use EscolaLms\TopicTypeProject\Events\ProjectSolutionCreatedEvent;
use EscolaLms\TopicTypeProject\Models\Project;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class ProjectSolutionTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions, WithFaker;

    public function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\EscolaLms\TopicTypeProject\EscolaLmsTopicTypeProjectServiceProvider::class)) {
            $this->markTestSkipped('TopicTypeProject package not installed');
        }

        $this->seed(TopicTypeProjectPermissionSeeder::class);
        Notification::fake();
        Mail::fake();
        Storage::fake();
    }

    public function testNotificationOnProjectSolutionCreated(): void
    {
        Event::fake([ProjectSolutionCreatedEvent::class]);

        $student = $this->makeStudent();

        $course = Course::factory()->state(['status' => CourseStatusEnum::PUBLISHED])->create();
        $course->users()->sync($student);
        $topic = Topic::factory()
            ->for(Lesson::factory()->state(['course_id' => $course->getKey()]))
            ->create();
        $users = User::factory()->count(3)->create();
        $project = Project::factory()->state(['notify_users' => $users->pluck('id')->toArray()])->create();
        $topic->topicable()->associate($project)->save();

        $this->actingAs($student, 'api')
            ->postJson('api/topic-project-solutions', [
                'topic_id' => $topic->getKey(),
                'file' => UploadedFile::fake()->create('solution.zip'),
            ])
            ->assertCreated();

        Event::assertDispatchedTimes(ProjectSolutionCreatedEvent::class, 3);
        Event::assertDispatched(ProjectSolutionCreatedEvent::class, function (ProjectSolutionCreatedEvent $event) use($users) {
            return $users->pluck('id')->contains($event->getUser()->getKey());
        });

        /** @var ProjectSolution $projectSolution */
        $projectSolution = ProjectSolution::latest()->first();
        $listener = app(TemplateEventListener::class);

        $users->each(function ($user) use ($listener, $projectSolution) {
            $listener->handle(new ProjectSolutionCreatedEvent($user, $projectSolution));
        });

        Mail::assertSent(EmailMailable::class, 3);
        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($users, $projectSolution) {
            $this->assertEquals(__('Solution has been submitted to the project ":topic"', ['topic' => $projectSolution->topic->title]), $mailable->subject);
            return $mailable->hasTo($users->pluck('email')->toArray());
        });
    }
}
