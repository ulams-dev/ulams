<?php

namespace EscolaLms\TopicTypeProject\Tests\Api\Admin;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\TopicTypeProject\Database\Seeders\TopicTypeProjectPermissionSeeder;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use EscolaLms\TopicTypeProject\Tests\TestCase;

class ProjectSolutionAdminDeleteApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TopicTypeProjectPermissionSeeder::class);
        $this->solution = ProjectSolution::factory()->create();
    }

    public function testAdminDeleteProjectSolutionUnauthorized(): void
    {
        $this->deleteJson('api/admin/topic-project-solutions/' . $this->solution->getKey())
            ->assertUnauthorized();
    }

    public function testAdminDeleteProjectSolution(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->deleteJson('api/admin/topic-project-solutions/' . $this->solution->getKey())
            ->assertOk();

        $this->assertDatabaseMissing('topic_project_solutions', [
            'id' => $this->solution->getKey(),
        ]);
    }
}
