<?php

namespace EscolaLms\ConsultationAccess\Http\Requests;

use EscolaLms\ConsultationAccess\Dtos\ConsultationAccessEnquiryDto;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="CreateConsultationAccessEnquiryRequest",
 *      required={"consultation_id"},
 *      @OA\Property(
 *          property="consultation_id",
 *          description="consultation_id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="description",
 *          description="description",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="title",
 *          description="title",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="related_type",
 *          description="related_type",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="related_id",
 *          description="related_id",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="proposed_terms",
 *          type="array",
 *          @OA\Items(
 *              type="string",
 *              format="date-time"
 *          )
 *      ),
 * )
 *
 */
class CreateConsultationAccessEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('createOwn', ConsultationAccessEnquiry::class);
    }

    public function rules(): array
    {
        return [
            'consultation_id' => ['required', 'integer', 'exists:consultations,id'],
            'proposed_terms' => ['required', 'array', 'min:1'],
            'proposed_terms.*' => ['required', 'date', 'after_or_equal:now'],
            'description' => ['nullable', 'string', 'max:255'],
            'related_type' => ['nullable', 'string', 'required_with:related_id'],
            'related_id' => ['nullable', 'integer', 'required_with:related_type'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toDto(): ConsultationAccessEnquiryDto
    {
        return ConsultationAccessEnquiryDto::instantiateFromRequest($this);
    }
}
