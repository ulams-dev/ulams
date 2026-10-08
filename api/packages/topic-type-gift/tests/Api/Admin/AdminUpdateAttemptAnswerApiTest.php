<?php

namespace EscolaLms\TopicTypeGift\Tests\Api\Admin;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\TopicTypeGift\Database\Seeders\TopicTypeGiftPermissionSeeder;
use EscolaLms\TopicTypeGift\Models\AttemptAnswer;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use EscolaLms\TopicTypeGift\Tests\TestCase;

class AdminUpdateAttemptAnswerApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TopicTypeGiftPermissionSeeder::class);
        $this->answer = AttemptAnswer::factory()->create();
    }

    public function testAdminUpdateAttemptAnswerUnauthorized(): void
    {
        $this->patchJson('api/admin/quiz-answers/' . $this->answer->getKey())
            ->assertUnauthorized();
    }

    public function testAdminUpdateAttemptAnswerForbidden(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->patchJson('api/admin/quiz-answers/' . $this->answer->getKey())
            ->assertForbidden();
    }

    public function testAdminUpdateAttemptAnswer(): void
    {
        $payload = [
            'score' => $this->faker->numberBetween(),
            'feedback' => $this->faker->text(),
        ];

        $this->actingAs($this->makeAdmin(), 'api')
            ->patchJson('api/admin/quiz-answers/' . $this->answer->getKey(), $payload)
            ->assertOk();

        $this->assertDatabaseHas('topic_gift_attempt_answers', array_merge($payload, [
            'id' => $this->answer->getKey(),
        ]));
    }
}
