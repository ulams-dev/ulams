<?php

namespace EscolaLms\Consultations\Http\Requests;

use EscolaLms\Consultations\Enum\ConsultationsPermissionsEnum;
use EscolaLms\Consultations\Enum\ConsultationTermStatusEnum;
use EscolaLms\Consultations\Models\Consultation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ScheduleConsultationRequest extends ConsultationRequest
{

    public function authorize(): bool
    {
        return Gate::allows('read', $this->getConsultation());
    }

    public function rules(): array
    {
        return [
            'date_from' => ['date'],
            'date_to' => ['date', 'after_or_equal:date_from'],
            'status' => ['string', Rule::in(ConsultationTermStatusEnum::getValues())],
        ];
    }
}
