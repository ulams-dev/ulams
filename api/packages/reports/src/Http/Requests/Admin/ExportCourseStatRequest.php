<?php

namespace EscolaLms\Reports\Http\Requests\Admin;

use EscolaLms\Courses\Models\Course;
use Illuminate\Validation\Rule;

class ExportCourseStatRequest extends CourseStatsRequest
{
    protected function prepareForValidation()
    {
        $this->merge([
            'course_id' => $this->route('course_id')
        ]);
    }

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', Rule::exists((new Course())->getTable(), 'id')],
            'stat' => ['required', 'string', Rule::in(config('reports.stats')[Course::class] ?? [])],
        ];
    }

    public function getStat(): string
    {
        return $this->get('stat');
    }
}
