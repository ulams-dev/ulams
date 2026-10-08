<?php

namespace EscolaLms\TopicTypeGift\Tests\Api\Admin;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\TopicTypeGift\Database\Seeders\TopicTypeGiftPermissionSeeder;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Tests\TestCase;

class AdminUpdateGiftQuizApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TopicTypeGiftPermissionSeeder::class);
    }

    public function testAdminUpdateGiftQuizUnauthorized(): void
    {
        $this
            ->putJson('api/admin/gift-quizes/123')
            ->assertUnauthorized();
    }

    public function testAdminUpdateGiftQuizForbidden(): void
    {
        $quiz = GiftQuiz::factory()->create();

        $this
            ->actingAs($this->makeStudent(), 'api')
            ->putJson('api/admin/gift-quizes/' . $quiz->getKey())
            ->assertForbidden();
    }

    public function testAdminUpdateGiftQuiz(): void
    {
        $quiz = GiftQuiz::factory()->create();
        $data = GiftQuiz::factory()->make()->toArray();

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->putJson('api/admin/gift-quizes/' . $quiz->getKey(), $data)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $quiz->getKey(),
                'questions' => [],
            ] + $data);
    }

    public function testAdminUpdateGiftQuizSetsCountsToGrade(): void
    {
        $quiz = GiftQuiz::factory()->create(['counts_to_grade' => false]);

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->putJson('api/admin/gift-quizes/' . $quiz->getKey(), [
                'value' => $quiz->value,
                'counts_to_grade' => true,
            ])
            ->assertOk()
            ->assertJsonFragment(['counts_to_grade' => true]);

        $this->assertDatabaseHas('topic_gift_quizzes', [
            'id' => $quiz->getKey(),
            'counts_to_grade' => true,
        ]);
    }

    public function testAdminUpdateGiftQuizCanDisableCountsToGrade(): void
    {
        $quiz = GiftQuiz::factory()->create(['counts_to_grade' => true]);

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->putJson('api/admin/gift-quizes/' . $quiz->getKey(), [
                'value' => $quiz->value,
                'counts_to_grade' => false,
            ])
            ->assertOk()
            ->assertJsonFragment(['counts_to_grade' => false]);

        $this->assertDatabaseHas('topic_gift_quizzes', [
            'id' => $quiz->getKey(),
            'counts_to_grade' => false,
        ]);
    }

    public function testAdminUpdateGiftQuizSetsWeight(): void
    {
        $quiz = GiftQuiz::factory()->create(['weight' => 1]);

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->putJson('api/admin/gift-quizes/' . $quiz->getKey(), [
                'value' => $quiz->value,
                'weight' => 5,
            ])
            ->assertOk()
            ->assertJsonFragment(['weight' => 5]);

        $this->assertDatabaseHas('topic_gift_quizzes', [
            'id' => $quiz->getKey(),
            'weight' => 5,
        ]);
    }

    public function testAdminUpdateGiftQuizKeepsCountsToGradeWhenOmitted(): void
    {
        $quiz = GiftQuiz::factory()->create(['counts_to_grade' => true]);

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->putJson('api/admin/gift-quizes/' . $quiz->getKey(), [
                'value' => $quiz->value,
            ])
            ->assertOk()
            ->assertJsonFragment(['counts_to_grade' => true]);

        $this->assertDatabaseHas('topic_gift_quizzes', [
            'id' => $quiz->getKey(),
            'counts_to_grade' => true,
        ]);
    }

    public function testAdminUpdateGiftQuizSetsRandomizeOrder(): void
    {
        $quiz = GiftQuiz::factory()->create(['randomize_order' => false]);

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->putJson('api/admin/gift-quizes/' . $quiz->getKey(), [
                'value' => $quiz->value,
                'randomize_order' => true,
            ])
            ->assertOk()
            ->assertJsonFragment(['randomize_order' => true]);

        $this->assertDatabaseHas('topic_gift_quizzes', [
            'id' => $quiz->getKey(),
            'randomize_order' => true,
        ]);
    }

    public function testAdminUpdateGiftQuizCanDisableRandomizeOrder(): void
    {
        $quiz = GiftQuiz::factory()->create(['randomize_order' => true]);

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->putJson('api/admin/gift-quizes/' . $quiz->getKey(), [
                'value' => $quiz->value,
                'randomize_order' => false,
            ])
            ->assertOk()
            ->assertJsonFragment(['randomize_order' => false]);

        $this->assertDatabaseHas('topic_gift_quizzes', [
            'id' => $quiz->getKey(),
            'randomize_order' => false,
        ]);
    }

    public function testAdminUpdateGiftQuizKeepsRandomizeOrderWhenOmitted(): void
    {
        $quiz = GiftQuiz::factory()->create(['randomize_order' => true]);

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->putJson('api/admin/gift-quizes/' . $quiz->getKey(), [
                'value' => $quiz->value,
            ])
            ->assertOk()
            ->assertJsonFragment(['randomize_order' => true]);

        $this->assertDatabaseHas('topic_gift_quizzes', [
            'id' => $quiz->getKey(),
            'randomize_order' => true,
        ]);
    }
}
