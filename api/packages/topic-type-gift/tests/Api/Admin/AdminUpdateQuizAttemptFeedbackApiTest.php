<?php

namespace EscolaLms\TopicTypeGift\Tests\Api\Admin;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\TopicTypeGift\Database\Seeders\TopicTypeGiftPermissionSeeder;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use EscolaLms\TopicTypeGift\Tests\TestCase;
use Illuminate\Support\Carbon;

class AdminUpdateQuizAttemptFeedbackApiTest extends TestCase
{
    use CreatesUsers;

    private QuizAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TopicTypeGiftPermissionSeeder::class);
        $this->attempt = QuizAttempt::factory()->create([
            'end_at' => Carbon::now()->subMinute(),
        ]);
    }

    public function testAdminUpdateQuizAttemptFeedbackUnauthorized(): void
    {
        $this->patchJson('api/admin/quiz-attempts/' . $this->attempt->getKey() . '/feedback')
            ->assertUnauthorized();
    }

    public function testAdminUpdateQuizAttemptFeedbackForbidden(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->patchJson('api/admin/quiz-attempts/' . $this->attempt->getKey() . '/feedback', [
                'feedback' => $this->faker->text(),
            ])
            ->assertForbidden();
    }

    public function testAdminAddsQuizAttemptFeedback(): void
    {
        $feedback = '<p>Good job, see the video: https://example.com/video</p>';

        $this->actingAs($this->makeAdmin(), 'api')
            ->patchJson('api/admin/quiz-attempts/' . $this->attempt->getKey() . '/feedback', [
                'feedback' => $feedback,
            ])
            ->assertOk();

        $this->assertDatabaseHas('topic_gift_quiz_attempts', [
            'id' => $this->attempt->getKey(),
            'tutor_feedback' => $feedback,
        ]);
    }

    public function testAdminEditQuizAttemptFeedbackOverwrites(): void
    {
        $admin = $this->makeAdmin();
        $url = 'api/admin/quiz-attempts/' . $this->attempt->getKey() . '/feedback';

        $this->actingAs($admin, 'api')->patchJson($url, ['feedback' => 'first version'])->assertOk();
        $this->actingAs($admin, 'api')->patchJson($url, ['feedback' => 'second version'])->assertOk();

        $this->assertSame('second version', $this->attempt->refresh()->tutor_feedback);
    }

    public function testAdminRemovesQuizAttemptFeedbackWithNull(): void
    {
        $this->attempt->update(['tutor_feedback' => 'to be removed']);

        $this->actingAs($this->makeAdmin(), 'api')
            ->patchJson('api/admin/quiz-attempts/' . $this->attempt->getKey() . '/feedback', [
                'feedback' => null,
            ])
            ->assertOk();

        $this->assertNull($this->attempt->refresh()->tutor_feedback);
    }

    public function testAdminRemovesQuizAttemptFeedbackWithEmptyString(): void
    {
        $this->attempt->update(['tutor_feedback' => 'to be removed']);

        $this->actingAs($this->makeAdmin(), 'api')
            ->patchJson('api/admin/quiz-attempts/' . $this->attempt->getKey() . '/feedback', [
                'feedback' => '',
            ])
            ->assertOk();

        $this->assertNull($this->attempt->refresh()->tutor_feedback);
    }

    public function testEndedAttemptExposesFeedbackToTutor(): void
    {
        $feedback = $this->faker->text();
        $this->attempt->update(['tutor_feedback' => $feedback]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts/' . $this->attempt->getKey())
            ->assertOk()
            ->assertJsonPath('data.tutor_feedback', $feedback);
    }

    public function testNotEndedAttemptHidesFeedback(): void
    {
        $attempt = QuizAttempt::factory()->create([
            'end_at' => Carbon::now()->addMinutes(20),
            'tutor_feedback' => $this->faker->text(),
        ]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/quiz-attempts/' . $attempt->getKey())
            ->assertOk()
            ->assertJsonPath('data.tutor_feedback', null);
    }

    public function testStudentSeesFeedbackForEndedAttempt(): void
    {
        $student = $this->makeStudent();
        $feedback = $this->faker->text();
        $attempt = QuizAttempt::factory()->create([
            'user_id' => $student->getKey(),
            'end_at' => Carbon::now()->subMinute(),
            'tutor_feedback' => $feedback,
        ]);

        $this->actingAs($student, 'api')
            ->getJson('api/quiz-attempts/' . $attempt->getKey())
            ->assertOk()
            ->assertJsonPath('data.tutor_feedback', $feedback);
    }
}
