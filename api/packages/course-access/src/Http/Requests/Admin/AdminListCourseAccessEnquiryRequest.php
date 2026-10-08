<?php

namespace Ulams\CourseAccess\Http\Requests\Admin;

use Ulams\Core\Dtos\PaginationDto;
use Ulams\CourseAccess\Dtos\CriteriaDto;
use Ulams\CourseAccess\Http\Requests\ListCourseAccessEnquiryRequest;
use Ulams\CourseAccess\Models\CourseAccessEnquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AdminListCourseAccessEnquiryRequest extends ListCourseAccessEnquiryRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', CourseAccessEnquiry::class);
    }
}
