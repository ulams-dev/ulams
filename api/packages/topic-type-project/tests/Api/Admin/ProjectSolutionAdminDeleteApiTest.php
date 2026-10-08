<?php

namespace Ulams\TopicTypeProject\Tests\Api\Admin;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\TopicTypeProject\Database\Seeders\TopicTypeProjectPermissionSeeder;
use Ulams\TopicTypeProject\Models\ProjectSolution;
use Ulams\TopicTypeProject\Tests\TestCase;

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
