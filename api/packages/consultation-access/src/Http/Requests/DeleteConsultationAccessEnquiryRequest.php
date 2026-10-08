<?php

namespace EscolaLms\ConsultationAccess\Http\Requests;

use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DeleteConsultationAccessEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('deleteOwn', $this->getEnquiry());
    }

    public function rules(): array
    {
        return [];
    }

    public function getId(): int
    {
        /** @var int $id */
        $id = $this->route('id');
        return $id;
    }

    private function getEnquiry(): ConsultationAccessEnquiry
    {
        return ConsultationAccessEnquiry::findOrFail($this->getId());
    }
}
