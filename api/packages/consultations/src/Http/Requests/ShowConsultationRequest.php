<?php

namespace EscolaLms\Consultations\Http\Requests;

use Illuminate\Support\Facades\Gate;

class ShowConsultationRequest extends ConsultationRequest
{
    public function authorize(): bool
    {
        return Gate::allows('read', $this->getConsultation());
    }

    public function rules(): array
    {
        return [];
    }
}
