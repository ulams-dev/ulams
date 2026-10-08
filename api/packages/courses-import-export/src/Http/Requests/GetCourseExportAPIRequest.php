<?php

namespace EscolaLms\CoursesImportExport\Http\Requests;

use EscolaLms\CoursesImportExport\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class GetCourseExportAPIRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $course = $this->getCourse();
        if (is_null($course)) {
            return true; // controller will fire 404 error
        }

        return Gate::check('export', $course);
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
        return Course::find($this->route('course'));
    }
}
