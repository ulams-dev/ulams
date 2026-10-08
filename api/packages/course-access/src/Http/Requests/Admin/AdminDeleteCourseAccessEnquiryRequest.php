<?php

namespace Ulams\CourseAccess\Http\Requests\Admin;

use Ulams\CourseAccess\Http\Requests\DeleteCourseAccessEnquiryRequest;
use Illuminate\Support\Facades\Gate;

class AdminDeleteCourseAccessEnquiryRequest extends DeleteCourseAccessEnquiryRequest
{
    public function authorize(): bool
    {
        return Gate::allows('delete', $this->getCourseAccessEnquiry());
    }
}
