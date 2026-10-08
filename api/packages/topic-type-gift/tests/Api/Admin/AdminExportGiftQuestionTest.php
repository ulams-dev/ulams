<?php

namespace EscolaLms\TopicTypeGift\Tests\Api\Admin;

use EscolaLms\Categories\Models\Category;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\TopicTypeGift\Database\Seeders\TopicTypeGiftPermissionSeeder;
use EscolaLms\TopicTypeGift\Export\QuestionExport;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Tests\TestCase;
use Maatwebsite\Excel\Facades\Excel;

class AdminExportGiftQuestionTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TopicTypeGiftPermissionSeeder::class);
    }

    public function testAdminExportGiftQuestionsUnauthorized(): void
    {
        $this->getJson('api/admin/gift-questions/export')
            ->assertUnauthorized();
    }

    public function testAdminExportGifQuestionsByCategory(): void
    {
        Excel::fake();
        GiftQuestion::factory()->count(5);

        $category = Category::factory()->create();
        GiftQuestion::factory()
            ->count(3)
            ->state(['category_id' => $category->getKey()])
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/gift-questions/export?category_ids[]=' . $category->getKey())
            ->assertOk();

        Excel::assertDownloaded('questions.xlsx', function (QuestionExport $export) {
            $this->assertCount(3, $export->collection());
            $this->assertEquals([__('Question'), __('Type'), __('Score')], $export->headings());
            return true;
        });
    }

    public function testAdminExportGifQuestionsByQuiz(): void
    {
        Excel::fake();
        GiftQuestion::factory()->count(5);

        $quiz = GiftQuiz::factory()
            ->has(GiftQuestion::factory()->count(3), 'questions')
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/gift-questions/export?topic_gift_quiz_id=' . $quiz->getKey())
            ->assertOk();

        Excel::assertDownloaded('questions.xlsx', function (QuestionExport $export) {
            $this->assertCount(3, $export->collection());
            return true;
        });
    }

    public function testAdminExportGifQuestionsByIds(): void
    {
        Excel::fake();
        GiftQuestion::factory()->count(5);

        $question1 = GiftQuestion::factory()->create();
        $question2 = GiftQuestion::factory()->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/gift-questions/export?ids[]=' . $question1->getKey() . '&ids[]=' . $question2->getKey())
            ->assertOk();

        Excel::assertDownloaded('questions.xlsx', function (QuestionExport $export) {
            $this->assertCount(2, $export->collection());
            return true;
        });
    }
}
