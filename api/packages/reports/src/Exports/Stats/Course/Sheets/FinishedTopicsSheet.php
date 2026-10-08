<?php

namespace EscolaLms\Reports\Exports\Stats\Course\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

abstract class FinishedTopicsSheet implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle, WithStrictNullComparison
{
    protected Collection $stats;
    public string $firstHeader = 'Email';

    public function __construct(Collection $stats)
    {
        $this->stats = $stats;
    }

    public function headings(): array
    {
        $headings = [
            __($this->firstHeader),
        ];

        if ($this->stats->isNotEmpty()) {
            $topics = collect($this->stats->first())
                ->get('topics')
                ->pluck('title')
                ->toArray();

            $headings = array_merge($headings, $topics);
        }

        return $headings;
    }
}
