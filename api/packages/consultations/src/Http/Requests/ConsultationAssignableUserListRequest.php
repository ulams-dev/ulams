<?php

namespace EscolaLms\Consultations\Http\Requests;

use EscolaLms\Consultations\Models\Consultation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ConsultationAssignableUserListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Consultation::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['string'],
        ];
    }
}
