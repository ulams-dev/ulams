<?php

namespace EscolaLms\CourseAccess\Http\Requests;

use EscolaLms\CourseAccess\Dtos\CourseAccessEnquiry\CreateCourseAccessEnquiryDto;
use EscolaLms\CourseAccess\Models\CourseAccessEnquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="CreateCourseAccessEnquiryApiRequest",
 *      required={"course_id"},
 *      @OA\Property(
 *          property="course_id",
 *          description="course_id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="data",
 *          description="data",
 *          type="object"
 *      ),
 * )
 *
 */
class CreateCourseAccessEnquiryApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('createOwn', CourseAccessEnquiry::class);
    }

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'data' => ['sometimes', 'array'],
        ];
    }

    public function getCreateCourseAccessEnquiryDto(): CreateCourseAccessEnquiryDto
    {
        return CreateCourseAccessEnquiryDto::instantiateFromRequest($this);
    }
}
