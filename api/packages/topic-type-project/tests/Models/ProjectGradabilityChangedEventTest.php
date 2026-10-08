<?php

namespace EscolaLms\TopicTypeProject\Tests\Models;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Database\Seeders\CoursesPermissionSeeder;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypeProject\Events\ProjectGradabilityChangedEvent;
use EscolaLms\TopicTypeProject\Models\Project;
use EscolaLms\TopicTypeProject\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class ProjectGradabilityChangedEventTest extends TestCase
{
    use CreatesUsers;

    public function testEmitsWhenCountsToGradeEnabled(): void
    {
        $project = Project::factory()->create(['counts_to_grade' => false]);

        Event::fake([ProjectGradabilityChangedEvent::class]);

        $project->update(['counts_to_grade' => true]);

        Event::assertDispatched(
            ProjectGradabilityChangedEvent::class,
            fn (ProjectGradabilityChangedEvent $event) => $event->getProject()->is($project) && $event->countsToGrade() === true
        );
    }

    public function testEmitsWhenCountsToGradeDisabled(): void
    {
        $project = Project::factory()->create(['counts_to_grade' => true]);

        Event::fake([ProjectGradabilityChangedEvent::class]);

        $project->update(['counts_to_grade' => false]);

        Event::assertDispatched(
            ProjectGradabilityChangedEvent::class,
            fn (ProjectGradabilityChangedEvent $event) => $event->getProject()->is($project) && $event->countsToGrade() === false
        );
    }

    public function testDoesNotEmitWhenCountsToGradeIsUnchanged(): void
    {
        $project = Project::factory()->create(['counts_to_grade' => true]);

        Event::fake([ProjectGradabilityChangedEvent::class]);

        $project->update(['value' => 'a different value', 'counts_to_grade' => true]);

        Event::assertNotDispatched(ProjectGradabilityChangedEvent::class);
    }

    public function testDoesNotEmitOnCreate(): void
    {
        Event::fake([ProjectGradabilityChangedEvent::class]);

        Project::factory()->create(['counts_to_grade' => true]);

        Event::assertNotDispatched(ProjectGradabilityChangedEvent::class);
    }

    public function testEmitsViaGenericTopicUpdateEndpoint(): void
    {
        $this->seed(CoursesPermissionSeeder::class);
        $project = Project::factory()->create(['counts_to_grade' => false]);
        $topic = Topic::factory()
            ->for(Lesson::factory()->for(Course::factory()))
            ->create([
                'topicable_type' => Project::class,
                'topicable_id' => $project->getKey(),
            ]);

        Event::fake([ProjectGradabilityChangedEvent::class]);

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/topics/' . $topic->getKey(), [
                'title' => $topic->title,
                'lesson_id' => $topic->lesson_id,
                'topicable_type' => Project::class,
                'value' => $project->value,
                'counts_to_grade' => true,
            ])
            ->assertOk();

        Event::assertDispatched(
            ProjectGradabilityChangedEvent::class,
            fn (ProjectGradabilityChangedEvent $event) => $event->getProject()->is($project) && $event->countsToGrade() === true
        );
    }
}
