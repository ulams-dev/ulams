<?php

namespace EscolaLms\Consultations\Http\Requests;

use EscolaLms\Consultations\Models\Consultation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListConsultationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', Consultation::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['string'],
            'status' => ['array'],
            'status.*' => ['string'],
            'order_by' => ['sometimes', 'string', 'in:id,name,status,duration,active_from,active_to,created_at'],
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer'],
        ];
    }
}
