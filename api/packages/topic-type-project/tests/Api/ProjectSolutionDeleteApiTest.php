<?php

namespace Ulams\TopicTypeProject\Tests\Api;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeProject\Database\Seeders\TopicTypeProjectPermissionSeeder;
use Ulams\TopicTypeProject\Models\Project;
use Ulams\TopicTypeProject\Models\ProjectSolution;
use Ulams\TopicTypeProject\Tests\TestCase;
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
