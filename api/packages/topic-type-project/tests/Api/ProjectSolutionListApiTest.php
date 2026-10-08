<?php

namespace EscolaLms\TopicTypeProject\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Enum\CourseStatusEnum;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypeProject\Database\Seeders\TopicTypeProjectPermissionSeeder;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use EscolaLms\TopicTypeProject\Tests\TestCase;
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
