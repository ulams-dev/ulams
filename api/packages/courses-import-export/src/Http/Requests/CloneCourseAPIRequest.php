<?php

namespace EscolaLms\CoursesImportExport\Http\Requests;

use EscolaLms\CoursesImportExport\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CloneCourseAPIRequest extends FormRequest
{
    public function authorize()
    {
        $course = $this->getCourse();
        return Gate::check('clone', $course);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }

    public function getCourse(): ?Course
    {
        return Course::findOrFail($this->route('course'));
    }
}
