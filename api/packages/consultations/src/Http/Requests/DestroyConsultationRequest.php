<?php

namespace EscolaLms\Consultations\Http\Requests;

use Illuminate\Support\Facades\Gate;

class DestroyConsultationRequest extends ConsultationRequest
{
    public function authorize(): bool
    {
        return Gate::allows('delete', $this->getConsultation());
    }

    public function rules(): array
    {
        return [];
    }
}
