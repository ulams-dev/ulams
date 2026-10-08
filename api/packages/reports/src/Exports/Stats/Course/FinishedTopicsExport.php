<?php

namespace EscolaLms\Reports\Exports\Stats\Course;

use EscolaLms\Courses\Models\Course;
use EscolaLms\Reports\Exports\Stats\Course\Sheets\FinishedTopicsAttemptsSheet;
use EscolaLms\Reports\Exports\Stats\Course\Sheets\FinishedTopicsInfoSheet;
use EscolaLms\Reports\Exports\Stats\Course\Sheets\FinishedTopicsSecondsSheet;
use EscolaLms\Reports\Exports\Stats\Course\Sheets\FinishedTopicsStatusesSheet;
use EscolaLms\Reports\Stats\Course\FinishedTopics;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FinishedTopicsExport implements WithMultipleSheets
{
    use Exportable;

    private Collection $data;

    public function __construct(Course $course)
    {
        $this->data = collect(FinishedTopics::make($course)->calculate())
            ->map(function ($item) {
                $item['topics'] = $item['topics']
                    ->sortBy('id')
                    ->unique('id');

                return $item;
            });
    }

    public function sheets(): array
    {
        return [
            new FinishedTopicsStatusesSheet($this->data),
            new FinishedTopicsSecondsSheet($this->data),
            new FinishedTopicsAttemptsSheet($this->data),
            new FinishedTopicsInfoSheet($this->data),
        ];
    }
}
