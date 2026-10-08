<?php

namespace EscolaLms\Reports\Imports\Stats\Course;

use EscolaLms\Courses\Models\Course;
use EscolaLms\Reports\Imports\Stats\Course\Sheets\FinishedTopicsAttemptsSheet;
use EscolaLms\Reports\Imports\Stats\Course\Sheets\FinishedTopicsSecondsSheet;
use EscolaLms\Reports\Imports\Stats\Course\Sheets\FinishedTopicsStatusesSheet;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FinishedTopicsImport implements WithMultipleSheets
{
    use Importable;

    protected Course $course;

    public function __construct(Course $course)
    {
        $this->course = $course;
    }

    public function sheets(): array
    {
        return [
            new FinishedTopicsStatusesSheet($this->course),
            new FinishedTopicsSecondsSheet($this->course),
            new FinishedTopicsAttemptsSheet($this->course),
        ];
    }
}
