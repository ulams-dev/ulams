<?php

namespace EscolaLms\ConsultationAccess\Http\Requests\Admin;

use EscolaLms\ConsultationAccess\Dtos\ApproveConsultationAccessEnquiryDto;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="AdminApproveConsultationAccessEnquiryRequest",
 *      @OA\Property(
 *          property="meeting_link",
 *          description="meeting_link",
 *          type="string"
 *      ),
 * )
 *
 */
class AdminApproveConsultationAccessEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('approve', ConsultationAccessEnquiry::class);
    }

    public function rules(): array
    {
        return [
            'proposed_term_id' => ['required', 'integer', 'exists:consultation_access_enquiry_proposed_terms,id'],
            'meeting_link' => ['nullable', 'string', 'url'],
        ];
    }

    public function getProposedTermId(): int
    {
        /** @var int $id */
        $id = $this->route('proposedTermId');
        return $id;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['proposed_term_id' => $this->getProposedTermId()]);
    }

    public function getApproveConsultationAccessEnquiryDto(): ApproveConsultationAccessEnquiryDto
    {
        return ApproveConsultationAccessEnquiryDto::instantiateFromRequest($this);
    }
}
