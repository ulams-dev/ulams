<?php

namespace Ulams\TopicTypeGift\Tests\Api;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\TopicTypeGift\Database\Seeders\TopicTypeGiftPermissionSeeder;
use Ulams\TopicTypeGift\Models\QuizAttempt;
use Ulams\TopicTypeGift\Tests\TestCase;

class QuizAttemptEndApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TopicTypeGiftPermissionSeeder::class);

        $this->student = $this->makeStudent();
        $this->attempt = QuizAttempt::factory()
            ->state(['user_id' => $this->student->getKey()])
            ->create();
    }

    public function testQuizAttemptEndUnauthorized(): void
    {
        $this->postJson('api/quiz-attempts/' . $this->attempt->getKey() . '/end')
            ->assertUnauthorized();
    }

    public function testQuizAttemptEndForbidden(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->postJson('api/quiz-attempts/' . $this->attempt->getKey() . '/end')
            ->assertForbidden();
    }

    public function testFinishQuizAttempt(): void
    {
        $this->assertFalse($this->attempt->isEnded());

        $this->actingAs($this->student, 'api')
            ->postJson('api/quiz-attempts/' . $this->attempt->getKey() . '/end')
            ->assertOk()
            ->assertJsonFragment([
                'is_ended' => true,
            ]);

        $this->assertTrue($this->attempt->refresh()->isEnded());
    }
}
