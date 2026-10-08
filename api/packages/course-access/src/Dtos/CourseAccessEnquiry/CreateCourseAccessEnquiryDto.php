<?php

namespace Ulams\CourseAccess\Dtos\CourseAccessEnquiry;

use Ulams\Core\Dtos\Contracts\InstantiateFromRequest;
use Ulams\CourseAccess\Enum\EnquiryStatusEnum;
use Illuminate\Http\Request;

class CreateCourseAccessEnquiryDto extends CourseAccessEnquiryDto implements InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            $request->input('course_id'),
            auth()->id(),
            $request->input('data', []),
            EnquiryStatusEnum::PENDING,
        );
    }
}
