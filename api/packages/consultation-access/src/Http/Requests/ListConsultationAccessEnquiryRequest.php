<?php

namespace EscolaLms\ConsultationAccess\Http\Requests;

use EscolaLms\ConsultationAccess\Dtos\CriteriaDto;
use EscolaLms\ConsultationAccess\Dtos\PageDto;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Dtos\PaginationDto;
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
