<?php

namespace EscolaLms\ConsultationAccess\Http\Requests\Admin;

use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="AdminDisapproveConsultationAccessEnquiryRequest",
 *      @OA\Property(
 *          property="message",
 *          description="message",
 *          type="string"
 *      ),
 * )
 */
class AdminDisapproveConsultationAccessEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('disapprove', ConsultationAccessEnquiry::class);
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function getConsultationAccessEnquiryId(): int
    {
        /** @var int $id */
        $id = $this->route('id');
        return $id;
    }
}
