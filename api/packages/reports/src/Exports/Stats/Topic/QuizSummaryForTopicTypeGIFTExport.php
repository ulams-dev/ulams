<?php

namespace EscolaLms\Reports\Exports\Stats\Topic;

use EscolaLms\Courses\Models\Topic;
use EscolaLms\Reports\Stats\Topic\QuizSummaryForTopicTypeGIFT;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class QuizSummaryForTopicTypeGIFTExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStrictNullComparison
{
    use Exportable;

    private Collection $headers;
    private Collection $data;

    public function __construct(Topic $topic)
    {
        $this->data = collect(QuizSummaryForTopicTypeGIFT::make($topic)->calculate());
        $this->headers = collect($this->data->shift());
    }

    public function collection(): Collection
    {
        return $this->data;
    }

    public function map($row): array
    {
        $result = [];

        foreach ($this->headers->keys() as $key) {
            $result[$key] = $row[$key] ?? null;
        }

        return $result;
    }

    public function headings(): array
    {
        return $this->headers->toArray();
    }
}
