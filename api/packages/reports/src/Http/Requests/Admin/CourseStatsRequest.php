<?php

namespace EscolaLms\Reports\Http\Requests\Admin;

use EscolaLms\Courses\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourseStatsRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('viewAny', Report::class) || $this->user()->can('update', $this->getCourse());
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'course_id' => $this->route('course_id')
        ]);
    }

    public function rules()
    {
        return [
            'course_id' => ['required', 'integer', Rule::exists((new Course())->getTable(), 'id')],
            'stats' => ['sometimes', 'array'],
            'stats.*' => ['string', Rule::in(config('reports.stats')[Course::class] ?? [])],
        ];
    }

    public function getCourseId(): int
    {
        return $this->validated()['course_id'];
    }

    public function getCourse(): Course
    {
        return Course::find($this->getCourseId());
    }

    public function getStats(): array
    {
        return $this->has('stats') ? $this->validated()['stats'] : [];
    }
}
