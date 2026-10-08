<?php

namespace EscolaLms\TopicTypeGift\Tests\Api\Admin;

use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Tests\Api\GiftQuestionTestCase;

class AdminDeleteGiftQuestionTest extends GiftQuestionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->question = GiftQuestion::factory()
            ->state(['topic_gift_quiz_id' => $this->quiz->getKey()])
            ->create();
    }

    public function testAdminDeleteGiftQuizUnauthorized(): void
    {
        $this->deleteJson('api/admin/gift-questions/' . $this->question->getKey())
            ->assertUnauthorized();
    }

    public function testAdminDeleteGiftQuiz(): void
    {
        $this->actingAs($this->admin, 'api')
            ->deleteJson('api/admin/gift-questions/' . $this->question->getKey())
            ->assertOk();

        $this->assertDatabaseMissing('topic_gift_questions', [
            'id' => $this->question->getKey(),
        ]);
    }
}
