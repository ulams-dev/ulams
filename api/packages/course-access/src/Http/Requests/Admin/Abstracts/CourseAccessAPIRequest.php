<?php

namespace EscolaLms\CourseAccess\Http\Requests\Admin\Abstracts;

use EscolaLms\CourseAccess\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class CourseAccessAPIRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return !empty($user) && $user->can('update', $this->getCourse());
    }

    public function getCourse(): Course
    {
        return Course::findOrFail($this->getCourseId());
    }

    public function rules(): array
    {
        return [
            'id' => ['required',  Rule::exists('courses', 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['id' => $this->getCourseId()]);
    }

    private function getCourseId(): int
    {
        return $this->route('id');
    }
}
