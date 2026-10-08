<?php

namespace Ulams\Reports\Imports\Stats\Course;

use Ulams\Courses\Models\Course;
use Ulams\Reports\Imports\Stats\Course\Sheets\FinishedTopicsAttemptsSheet;
use Ulams\Reports\Imports\Stats\Course\Sheets\FinishedTopicsSecondsSheet;
use Ulams\Reports\Imports\Stats\Course\Sheets\FinishedTopicsStatusesSheet;
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
