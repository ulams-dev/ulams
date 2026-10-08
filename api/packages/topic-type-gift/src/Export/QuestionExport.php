<?php

namespace EscolaLms\TopicTypeGift\Export;

use EscolaLms\TopicTypeGift\Dtos\Criteria\ExportQuestionsCriteriaDto;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Repositories\Contracts\GiftQuestionRepositoryContract;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class QuestionExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStrictNullComparison
{
    use Exportable;

    private ExportQuestionsCriteriaDto $criteria;

    public function __construct(ExportQuestionsCriteriaDto $criteriaDto)
    {
       $this->criteria = $criteriaDto;
    }

    public function collection(): Collection
    {
        return app(GiftQuestionRepositoryContract::class)
            ->searchByCriteria($this->criteria->toArray())
            ->map(function (GiftQuestion $question) {
                return [
                    'value' => $question->value,
                    'type' => $question->type,
                    'score' => $question->score,
                ];
            });
    }

    public function headings(): array
    {
        return [
            __('Question'),
            __('Type'),
            __('Score'),
        ];
    }
}
