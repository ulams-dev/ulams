<?php

namespace Ulams\TopicTypeProject\Tests\Services;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeProject\Dtos\GradeProjectSolutionDto;
use Ulams\TopicTypeProject\Events\ProjectSolutionGradedEvent;
use Ulams\TopicTypeProject\Models\Project;
use Ulams\TopicTypeProject\Models\ProjectSolution;
use Ulams\TopicTypeProject\Services\Contracts\ProjectSolutionServiceContract;
use Ulams\TopicTypeProject\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class ProjectSolutionGradedEventTest extends TestCase
{
    use CreatesUsers;

    private ProjectSolutionServiceContract $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProjectSolutionServiceContract::class);
    }

    private function makeSolution(bool $countsToGrade): ProjectSolution
    {
        $project = Project::factory()->create([
            'counts_to_grade' => $countsToGrade,
            'max_score' => 10,
        ]);
        $topic = Topic::factory()
            ->for(Lesson::factory()->for(Course::factory()))
            ->create();
        $topic->topicable()->associate($project)->save();

        return ProjectSolution::factory()->create(['topic_id' => $topic->getKey()]);
    }

    public function testEmitsGradedEventWhenProjectCountsToGrade(): void
    {
        $solution = $this->makeSolution(true);
        $gradedBy = $this->makeAdmin();

        Event::fake([ProjectSolutionGradedEvent::class]);

        $this->service->grade($solution->getKey(), new GradeProjectSolutionDto(7.5, $gradedBy->getKey()));

        Event::assertDispatched(
            ProjectSolutionGradedEvent::class,
            fn (ProjectSolutionGradedEvent $event) => $event->getProjectSolution()->getKey() === $solution->getKey()
                && $event->getUser()->getKey() === $solution->user->getKey()
        );
    }

    public function testDoesNotEmitGradedEventWhenProjectDoesNotCountToGrade(): void
    {
        $solution = $this->makeSolution(false);
        $gradedBy = $this->makeAdmin();

        Event::fake([ProjectSolutionGradedEvent::class]);

        $this->service->grade($solution->getKey(), new GradeProjectSolutionDto(7.5, $gradedBy->getKey()));

        Event::assertNotDispatched(ProjectSolutionGradedEvent::class);
    }

    public function testReGradingEmitsGradedEventAgain(): void
    {
        $solution = $this->makeSolution(true);
        $gradedBy = $this->makeAdmin();

        Event::fake([ProjectSolutionGradedEvent::class]);

        $this->service->grade($solution->getKey(), new GradeProjectSolutionDto(3.0, $gradedBy->getKey()));
        $this->service->grade($solution->getKey(), new GradeProjectSolutionDto(9.0, $gradedBy->getKey()));

        Event::assertDispatchedTimes(ProjectSolutionGradedEvent::class, 2);
    }
}
