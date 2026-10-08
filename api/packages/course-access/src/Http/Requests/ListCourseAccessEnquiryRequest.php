<?php

namespace EscolaLms\CourseAccess\Http\Requests;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Dtos\PaginationDto;
use EscolaLms\CourseAccess\Dtos\CriteriaDto;
use EscolaLms\CourseAccess\Models\CourseAccessEnquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListCourseAccessEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('listOwn', CourseAccessEnquiry::class);
    }

    public function rules(): array
    {
        return [];
    }

    public function getCriteriaDto(): CriteriaDto
    {
        return CriteriaDto::instantiateFromRequest($this);
    }

    public function getPaginationDto(): PaginationDto
    {
        return PaginationDto::instantiateFromRequest($this);
    }

    public function getOrderDto(): OrderDto
    {
        return OrderDto::instantiateFromRequest($this);
    }
}
