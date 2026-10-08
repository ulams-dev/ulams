<?php

namespace EscolaLms\CoursesImportExport\Http\Requests;

use EscolaLms\CoursesImportExport\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CourseImportAPIRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::check('import', Course::class);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'mimes:zip'],
        ];
    }
}
