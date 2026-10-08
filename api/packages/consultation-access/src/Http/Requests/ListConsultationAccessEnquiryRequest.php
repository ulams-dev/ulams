<?php

namespace Ulams\ConsultationAccess\Http\Requests;

use Ulams\ConsultationAccess\Dtos\CriteriaDto;
use Ulams\ConsultationAccess\Dtos\PageDto;
use Ulams\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Dtos\PaginationDto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListConsultationAccessEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('listOwn', ConsultationAccessEnquiry::class);
    }

    public function rules(): array
    {
        return [
            'order' => ['sometimes', 'string', 'in:ASC,DESC'],
            'order_by' => ['sometimes', 'string', 'in:id,consultation_id,status,description,user_id,meeting_link,created_at,term_date'],
            'consultation_term_ids' => ['sometimes', 'array'],
            'consultation_term_ids.*' => ['integer'],
        ];
    }

    public function getCriteriaDto(): CriteriaDto
    {
        return CriteriaDto::instantiateFromRequest($this);
    }

    public function getPaginationDto(): PageDto
    {
        return PageDto::instantiateFromRequest($this);
    }

    public function getOrderDto(): OrderDto
    {
        return OrderDto::instantiateFromRequest($this);
    }
}
