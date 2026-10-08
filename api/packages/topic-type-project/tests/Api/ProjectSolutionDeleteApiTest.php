<?php

namespace EscolaLms\TopicTypeProject\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Enum\CourseStatusEnum;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypeProject\Database\Seeders\TopicTypeProjectPermissionSeeder;
use EscolaLms\TopicTypeProject\Models\Project;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use EscolaLms\TopicTypeProject\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class ProjectSolutionDeleteApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->seed(TopicTypeProjectPermissionSeeder::class);

        $this->solution = ProjectSolution::factory()->create();
    }

    public function testDeleteProjectSolutionUnauthorized(): void
    {
        $this->deleteJson('api/topic-project-solutions/' . $this->solution->getKey())
            ->assertUnauthorized();
    }

    public function testCreateProjectSolutionForbidden(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->deleteJson('api/topic-project-solutions/' . $this->solution->getKey())
            ->assertForbidden();
    }

    public function testDeleteProjectSolution(): void
    {
        $student = $this->makeStudent();
        $this->solution = ProjectSolution::factory()
            ->state(['user_id' => $student->getKey()])
            ->create();

        $this->actingAs($student, 'api')
            ->deleteJson('api/topic-project-solutions/' . $this->solution->getKey())
            ->assertOk();


        $this->assertDatabaseMissing('topic_project_solutions', [
            'id' => $this->solution->getKey(),
        ]);
    }
}
