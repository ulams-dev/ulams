<?php

namespace EscolaLms\ConsultationAccess\Http\Requests;

use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReadConsultationAccessEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('readOwn', $this->getEnquiry());
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

    public function getEnquiry(): ConsultationAccessEnquiry
    {
        return ConsultationAccessEnquiry::findOrFail($this->getId());
    }
}
