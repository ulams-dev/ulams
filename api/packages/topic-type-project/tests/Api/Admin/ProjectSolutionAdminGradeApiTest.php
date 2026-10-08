<?php

namespace EscolaLms\TopicTypeProject\Tests\Api\Admin;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypeProject\Database\Seeders\TopicTypeProjectPermissionSeeder;
use EscolaLms\TopicTypeProject\Models\Project;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use EscolaLms\TopicTypeProject\Tests\TestCase;

class ProjectSolutionAdminGradeApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TopicTypeProjectPermissionSeeder::class);
    }

    private function makeSolution(?int $maxScore = 10): ProjectSolution
    {
        $project = Project::factory()->create(['max_score' => $maxScore]);
        $topic = Topic::factory()
            ->for(Lesson::factory()->for(Course::factory()))
            ->create();
        $topic->topicable()->associate($project)->save();

        return ProjectSolution::factory()->create(['topic_id' => $topic->getKey()]);
    }

    private function url(ProjectSolution $solution): string
    {
        return 'api/admin/topic-project-solutions/' . $solution->getKey() . '/grade';
    }

    public function testAdminGradeProjectSolutionUnauthorized(): void
    {
        $solution = $this->makeSolution();

        $this->patchJson($this->url($solution), ['score' => 5])
            ->assertUnauthorized();
    }

    public function testAdminGradeProjectSolutionForbidden(): void
    {
        $solution = $this->makeSolution();

        $this->actingAs($this->makeStudent(), 'api')
            ->patchJson($this->url($solution), ['score' => 5])
            ->assertForbidden();
    }

    public function testAdminGradeProjectSolution(): void
    {
        $admin = $this->makeAdmin();
        $solution = $this->makeSolution(10);

        $data = $this->actingAs($admin, 'api')
            ->patchJson($this->url($solution), ['score' => 7.5])
            ->assertOk()
            ->json('data');

        $this->assertEquals(7.5, $data['score']);
        $this->assertEquals(10, $data['max_score']);

        $this->assertDatabaseHas('topic_project_solutions', [
            'id' => $solution->getKey(),
            'score' => 7.5,
            'graded_by' => $admin->getKey(),
        ]);
        $this->assertNotNull($solution->fresh()->graded_at);
    }

    public function testAdminGradeProjectSolutionOverwritesPreviousGrade(): void
    {
        $admin = $this->makeAdmin();
        $solution = $this->makeSolution(10);

        $this->actingAs($admin, 'api')
            ->patchJson($this->url($solution), ['score' => 3])
            ->assertOk();

        $score = $this->actingAs($admin, 'api')
            ->patchJson($this->url($solution), ['score' => 9])
            ->assertOk()
            ->json('data.score');

        $this->assertEquals(9, $score);

        $this->assertDatabaseHas('topic_project_solutions', [
            'id' => $solution->getKey(),
            'score' => 9,
        ]);
    }

    public function testAdminGradeProjectSolutionRejectsScoreGreaterThanProjectMax(): void
    {
        $solution = $this->makeSolution(10);

        $this->actingAs($this->makeAdmin(), 'api')
            ->patchJson($this->url($solution), ['score' => 11])
            ->assertStatus(422);
    }

    public function testAdminGradeProjectSolutionRejectsNegativeScore(): void
    {
        $solution = $this->makeSolution(10);

        $this->actingAs($this->makeAdmin(), 'api')
            ->patchJson($this->url($solution), ['score' => -1])
            ->assertStatus(422);
    }

    public function testAdminGradeProjectSolutionRejectsWhenProjectHasNoMaxScore(): void
    {
        $solution = $this->makeSolution(null);

        $this->actingAs($this->makeAdmin(), 'api')
            ->patchJson($this->url($solution), ['score' => 5])
            ->assertStatus(422);
    }
}
