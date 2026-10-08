<?php

namespace EscolaLms\TopicTypeProject\Tests\Api\Admin;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\TopicTypeProject\Database\Seeders\TopicTypeProjectPermissionSeeder;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use EscolaLms\TopicTypeProject\Tests\TestCase;

class AdminProjectSolutionFeedbackApiTest extends TestCase
{
    use CreatesUsers;

    private ProjectSolution $solution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TopicTypeProjectPermissionSeeder::class);
        $this->solution = ProjectSolution::factory()->create();
    }

    public function testAdminUpdateProjectSolutionFeedbackUnauthorized(): void
    {
        $this->patchJson('api/admin/topic-project-solutions/' . $this->solution->getKey() . '/feedback')
            ->assertUnauthorized();
    }

    public function testAdminUpdateProjectSolutionFeedbackForbidden(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->patchJson('api/admin/topic-project-solutions/' . $this->solution->getKey() . '/feedback', [
                'feedback' => $this->faker->text(),
            ])
            ->assertForbidden();
    }

    public function testAdminReadProjectSolutionReturnsFeedback(): void
    {
        $feedback = '<p>Good job, see the video: https://example.com/video</p>';
        $this->solution->update(['tutor_feedback' => $feedback]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/topic-project-solutions/' . $this->solution->getKey())
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

    public function testAdminAddsProjectSolutionFeedback(): void
    {
        $feedback = '<p>Good job, see the video: https://example.com/video</p>';

        $this->actingAs($this->makeAdmin(), 'api')
            ->patchJson('api/admin/topic-project-solutions/' . $this->solution->getKey() . '/feedback', [
                'feedback' => $feedback,
            ])
            ->assertOk()
            ->assertJsonPath('data.tutor_feedback', $feedback);

        $this->assertDatabaseHas('topic_project_solutions', [
            'id' => $this->solution->getKey(),
            'tutor_feedback' => $feedback,
        ]);
    }

    public function testAdminEditProjectSolutionFeedbackOverwrites(): void
    {
        $admin = $this->makeAdmin();
        $url = 'api/admin/topic-project-solutions/' . $this->solution->getKey() . '/feedback';

        $this->actingAs($admin, 'api')->patchJson($url, ['feedback' => 'first version'])->assertOk();
        $this->actingAs($admin, 'api')->patchJson($url, ['feedback' => 'second version'])->assertOk();

        $this->assertSame('second version', $this->solution->refresh()->tutor_feedback);
    }

    public function testAdminRemovesProjectSolutionFeedbackWithNull(): void
    {
        $this->solution->update(['tutor_feedback' => 'to be removed']);

        $this->actingAs($this->makeAdmin(), 'api')
            ->patchJson('api/admin/topic-project-solutions/' . $this->solution->getKey() . '/feedback', [
                'feedback' => null,
            ])
            ->assertOk();

        $this->assertNull($this->solution->refresh()->tutor_feedback);
    }

    public function testAdminRemovesProjectSolutionFeedbackWithEmptyString(): void
    {
        $this->solution->update(['tutor_feedback' => 'to be removed']);

        $this->actingAs($this->makeAdmin(), 'api')
            ->patchJson('api/admin/topic-project-solutions/' . $this->solution->getKey() . '/feedback', [
                'feedback' => '',
            ])
            ->assertOk();

        $this->assertNull($this->solution->refresh()->tutor_feedback);
    }
}
