<?php

namespace EscolaLms\ConsultationAccess\Http\Requests;

use EscolaLms\ConsultationAccess\Dtos\ConsultationAccessEnquiryDto;
use EscolaLms\ConsultationAccess\Dtos\UpdateConsultationAccessEnquiryDto;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="UpdateConsultationAccessEnquiryRequest",
 *      required={"proposed_terms"},
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
class UpdateConsultationAccessEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('updateOwn', $this->getEnquiry());
    }

    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:255'],
            'proposed_terms' => ['required', 'array', 'min:1'],
            'proposed_terms.*' => ['required', 'date', 'after_or_equal:now'],
            'related_type' => ['nullable', 'string', 'required_with:related_id'],
            'related_id' => ['nullable', 'integer', 'required_with:related_type'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function getId(): int
    {
        /** @var int $id */
        $id = $this->route('id');
        return $id;
    }

    public function toDto(): UpdateConsultationAccessEnquiryDto
    {
        return UpdateConsultationAccessEnquiryDto::instantiateFromRequest($this);
    }

    private function getEnquiry(): ConsultationAccessEnquiry
    {
        return ConsultationAccessEnquiry::findOrFail($this->getId());
    }
}
