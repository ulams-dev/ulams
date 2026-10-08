<?php

namespace Ulams\Consultations\Http\Requests;

use Ulams\Consultations\Exceptions\ConsultationNotFound;
use Ulams\Consultations\Models\Consultation;
use Illuminate\Foundation\Http\FormRequest;

abstract class ConsultationRequest extends FormRequest
{
    public function getConsultation(?int $id = null): Consultation
    {
        $consultation = Consultation::find($id ?? $this->getRouteIdParameter());

        if (!$consultation) {
            throw new ConsultationNotFound();
        }

        return $consultation;
    }

    public function rules(): array
    {
        return [];
    }

    private function getRouteIdParameter(): ?int
    {
        $result = $this->route('consultation') ?? $this->route('id');
        return $result ? (int) $result : null;
    }
}
