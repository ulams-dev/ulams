<?php

namespace Ulams\TopicTypeProject\Tests\Api;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeProject\Database\Seeders\TopicTypeProjectPermissionSeeder;
use Ulams\TopicTypeProject\Models\ProjectSolution;
use Ulams\TopicTypeProject\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class ProjectSolutionListApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->seed(TopicTypeProjectPermissionSeeder::class);
    }

    public function testListProjectSolutionUnauthorized(): void
    {
        $this->getJson('api/topic-project-solutions')
            ->assertUnauthorized();
    }

    public function testListProjectSolution(): void
    {
        $student = $this->makeStudent();

        ProjectSolution::factory()
            ->state(['user_id' => $student->getKey()])
            ->count(3)
            ->create();

        ProjectSolution::factory()
            ->count(2)
            ->create();

        $this->actingAs($student, 'api')->getJson('api/topic-project-solutions')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'created_at',
                    'topic_id',
                    'user_id',
                    'file_url',
                ]],
            ]);
    }

    public function testListProjectSolutionFiltering(): void
    {
        $student = $this->makeStudent();
        $course = Course::factory()->state(['status' => CourseStatusEnum::PUBLISHED])->create();
        $lesson = Lesson::factory()->state(['course_id' => $course->getKey()])->create();
        $topic = Topic::factory()->state(['lesson_id' => $lesson->getKey()])->create();

        ProjectSolution::factory()
            ->state([
                'user_id' => $student->getKey(),
                'topic_id' => $topic->getKey(),
            ])
            ->count(4)
            ->create();

        ProjectSolution::factory()
            ->state(['user_id' => $student->getKey()])
            ->count(2)
            ->create();

        $this->actingAs($student, 'api')
            ->getJson('api/topic-project-solutions')
            ->assertOk()
            ->assertJsonCount(6, 'data');

        $this->actingAs($student, 'api')
            ->getJson('api/topic-project-solutions?course_id=' . $course->getKey())
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }
}
