<?php

namespace Ulams\Courses\Http\Requests;

use Ulams\Courses\Enum\CoursesPermissionsEnum;
use Ulams\Courses\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CourseAssignableUserListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(CoursesPermissionsEnum::COURSE_CREATE, Course::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['string'],
        ];
    }
}
