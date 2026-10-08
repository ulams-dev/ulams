<?php

namespace EscolaLms\TopicTypeProject\Tests\Api\Admin;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\TopicTypeProject\Database\Seeders\TopicTypeProjectPermissionSeeder;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use EscolaLms\TopicTypeProject\Tests\TestCase;

class ProjectSolutionAdminListApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TopicTypeProjectPermissionSeeder::class);
    }

    public function testAdminListProjectSolutionUnauthorized(): void
    {
        $this->getJson('api/admin/topic-project-solutions')
            ->assertUnauthorized();
    }

    public function testAdminListProjectSolution(): void
    {
        $student = $this->makeStudent();
        ProjectSolution::factory()
            ->state(['user_id' => $student->getKey()])
            ->count(3)
            ->create();

        ProjectSolution::factory()
            ->count(2)
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/topic-project-solutions?user_id=' . $student->getKey())
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

    public function testAdminListProjectSolutionPagination(): void
    {
        $admin = $this->makeAdmin();
        ProjectSolution::factory()->count(35)->create();

        $this->actingAs($admin, 'api')
            ->getJson('api/admin/topic-project-solutions?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJson([
                'meta' => [
                    'total' => 35
                ]
            ]);

        $this->actingAs($admin, 'api')
            ->getJson('api/admin/topic-project-solutions?per_page=10&page=4')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJson([
                'meta' => [
                    'total' => 35
                ]
            ]);
    }
}
