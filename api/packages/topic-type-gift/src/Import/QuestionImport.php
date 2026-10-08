<?php

namespace EscolaLms\TopicTypeGift\Import;

use EscolaLms\TopicTypeGift\Dtos\GiftQuestionDto;
use EscolaLms\TopicTypeGift\Services\Contracts\GiftQuestionServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class QuestionImport implements ToCollection, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    private int $quizId;

    public function __construct(int $quizId)
    {
        HeadingRowFormatter::default('slug');
        $this->quizId = $quizId;
    }

    public function collection(Collection $collection): void
    {
        DB::transaction(function () use ($collection) {
            /** @var GiftQuestionServiceContract $service */
            $service = app(GiftQuestionServiceContract::class);

            foreach ($collection as $item) {
                $dto = new GiftQuestionDto($this->quizId, $item->get('question'), $item->get('score'), null, null);
                $service->create($dto);
            }
        });
    }

    public function rules(): array
    {
        return [
            '*.question' => ['required', 'string'],
            '*.score' => ['required', 'integer', 'min:0'],
        ];
    }
}
