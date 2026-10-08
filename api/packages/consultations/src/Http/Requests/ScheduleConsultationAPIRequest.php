<?php

namespace EscolaLms\Consultations\Http\Requests;

use EscolaLms\Consultations\Enum\ConsultationTermStatusEnum;
use EscolaLms\Consultations\Models\Consultation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ScheduleConsultationAPIRequest extends ConsultationRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', Consultation::class);
    }

    public function rules(): array
    {
        return [
            'date_from' => ['date'],
            'date_to' => ['date', 'after_or_equal:date_from'],
            'status' => ['string', Rule::in(ConsultationTermStatusEnum::getValues())],
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer'],
        ];
    }
}
