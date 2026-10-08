<?php

namespace Ulams\CourseAccess\Http\Requests;

use Ulams\CourseAccess\Models\CourseAccessEnquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DeleteCourseAccessEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('deleteOwn', $this->getCourseAccessEnquiry());
    }

    public function rules(): array
    {
        return [];
    }

    public function getCourseAccessEnquiry(): CourseAccessEnquiry
    {
        return CourseAccessEnquiry::findOrFail($this->route('id'));
    }
}
