<?php

namespace EscolaLms\TopicTypeGift\Tests\Api\Admin;

use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Tests\Api\GiftQuestionTestCase;

class AdminCreateGiftQuestionTest extends GiftQuestionTestCase
{
    public function testAdminCreateGiftQuestionUnauthorized(): void
    {
        $this->postJson('api/admin/gift-questions')
            ->assertUnauthorized();
    }

    public function testAdminCreateGiftQuestion(): void
    {
        $question = GiftQuestion::factory()->make();

        $this->actingAs($this->admin, 'api')
            ->postJson('api/admin/gift-questions', [
                'topic_gift_quiz_id' => $this->quiz->getKey(),
                'value' => $question->value,
                'score' => $question->score,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('topic_gift_questions', [
            'topic_gift_quiz_id' => $this->quiz->getKey(),
            'value' => $question->value,
            'score' => $question->score,
        ]);
    }
}
