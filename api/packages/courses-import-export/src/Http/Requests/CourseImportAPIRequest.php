<?php

namespace Ulams\CoursesImportExport\Http\Requests;

use Ulams\CoursesImportExport\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Ulams\Uploads\Rules\SafeUpload;

class CourseImportAPIRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::check('import', Course::class);
    }

    public function rules(): array
    {
        return [
            'file' => ['bail', 'required', 'file', 'mimes:zip', new SafeUpload('course-import')],
        ];
    }
}
