<?php

namespace EscolaLms\TopicTypeGift\Tests\Api\Admin;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\TopicTypeGift\Database\Seeders\TopicTypeGiftPermissionSeeder;
use EscolaLms\TopicTypeGift\Enum\QuestionTypeEnum;
use EscolaLms\TopicTypeGift\Import\QuestionImport;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class AdminImportGiftQuestionTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TopicTypeGiftPermissionSeeder::class);
    }

    public function testAdminImportGiftQuestionsUnauthorized(): void
    {
        $this
            ->postJson('api/admin/gift-questions/import')
            ->assertUnauthorized();
    }

    public function testAdminImportGiftQuestionsRequiredValidation(): void
    {
        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/gift-questions/import')
            ->assertJsonValidationErrors(['topic_gift_quiz_id', 'file']);
    }

    public function testAdminImportGiftQuestions(): void
    {
        Excel::fake();

        /** @var GiftQuiz $quiz */
        $quiz = GiftQuiz::factory()->create();

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/gift-questions/import', [
                'topic_gift_quiz_id' => $quiz->getKey(),
                'file' => UploadedFile::fake()->create('questions.xlsx'),
            ]);

        $data = $this->prepareImportData();

        Excel::assertImported('questions.xlsx', function (QuestionImport $import) use ($data) {
            $import->collection($data);
            $this->assertEquals([
                '*.question' => ['required', 'string'],
                '*.score' => ['required', 'integer', 'min:0'],
            ], $import->rules());

            return true;
        });

        $quiz->refresh();
        $this->assertCount(2, $quiz->questions);
    }

    private function prepareImportData(): Collection
    {
        $collection = collect();

        $collection->push(collect([
            'question' => 'Who\'s buried in Grant\'s tomb?{=Grant ~no one ~Napoleon ~Churchill ~Mother Teresa }',
            'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
            'score' => $this->faker->numberBetween(1, 10),
        ]));

        $collection->push(collect([
            'question' => '::TrueStatement about Grant::Grant was buried in a tomb in New York City.{T}',
            'type' => QuestionTypeEnum::TRUE_FALSE,
            'score' => $this->faker->numberBetween(1, 10),
        ]));


        return $collection;
    }
}
