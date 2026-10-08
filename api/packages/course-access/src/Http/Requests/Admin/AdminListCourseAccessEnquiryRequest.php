<?php

namespace EscolaLms\CourseAccess\Http\Requests\Admin;

use EscolaLms\Core\Dtos\PaginationDto;
use EscolaLms\CourseAccess\Dtos\CriteriaDto;
use EscolaLms\CourseAccess\Http\Requests\ListCourseAccessEnquiryRequest;
use EscolaLms\CourseAccess\Models\CourseAccessEnquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AdminListCourseAccessEnquiryRequest extends ListCourseAccessEnquiryRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', CourseAccessEnquiry::class);
    }
}
