<?php

namespace Ulams\Consultations\Http\Requests;

use Ulams\Consultations\Models\Consultation;
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
