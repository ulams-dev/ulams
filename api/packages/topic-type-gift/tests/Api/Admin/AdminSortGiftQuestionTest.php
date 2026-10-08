<?php

namespace EscolaLms\TopicTypeGift\Tests\Api\Admin;

use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Tests\Api\GiftQuestionTestCase;

class AdminSortGiftQuestionTest extends GiftQuestionTestCase
{
    public function testAdminSortGiftQuizUnauthorized(): void
    {
        $this->deleteJson('api/admin/gift-questions/sort')
            ->assertUnauthorized();
    }

    public function testAdminSortGiftQuestion(): void
    {
        $question1 = GiftQuestion::factory()
            ->state(['topic_gift_quiz_id' => $this->quiz->getKey()])
            ->create();

        $question2 = GiftQuestion::factory()
            ->state(['topic_gift_quiz_id' => $this->quiz->getKey()])
            ->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('api/admin/gift-questions/sort', [
                'orders' => [
                    [
                        'id' => $question1->getKey(),
                        'order' => 12,
                    ], [
                        'id' => $question2->getKey(),
                        'order' => 13,
                    ],
                ],
            ])->assertOk();

        $this->assertEquals(12, $question1->refresh()->order);
        $this->assertEquals(13, $question2->refresh()->order);
    }
}
