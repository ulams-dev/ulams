<?php

namespace EscolaLms\TopicTypeProject\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\TopicTypeProject\Database\Seeders\TopicTypeProjectPermissionSeeder;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use EscolaLms\TopicTypeProject\Tests\TestCase;

class ProjectSolutionReadApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TopicTypeProjectPermissionSeeder::class);
    }

    public function testReadProjectSolutionUnauthorized(): void
    {
        $solution = ProjectSolution::factory()->create();

        $this->getJson('api/topic-project-solutions/' . $solution->getKey())
            ->assertUnauthorized();
    }

    public function testStudentReadsOwnProjectSolution(): void
    {
        $student = $this->makeStudent();
        $feedback = $this->faker->text();
        $solution = ProjectSolution::factory()->create([
            'user_id' => $student->getKey(),
            'tutor_feedback' => $feedback,
        ]);

        $this->actingAs($student, 'api')
            ->getJson('api/topic-project-solutions/' . $solution->getKey())
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'created_at',
                    'topic_id',
                    'user_id',
                    'file_url',
                    'tutor_feedback',
                ],
            ])
            ->assertJsonPath('data.tutor_feedback', $feedback);
    }

    public function testStudentCannotReadOthersProjectSolution(): void
    {
        $solution = ProjectSolution::factory()->create([
            'user_id' => $this->makeStudent()->getKey(),
        ]);

        $this->actingAs($this->makeStudent(), 'api')
            ->getJson('api/topic-project-solutions/' . $solution->getKey())
            ->assertForbidden();
    }
}
