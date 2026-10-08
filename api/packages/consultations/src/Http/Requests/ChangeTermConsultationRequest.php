<?php

namespace Ulams\Consultations\Http\Requests;

use Ulams\Consultations\Enum\ConsultationsPermissionsEnum;
use Ulams\Consultations\Models\ConsultationUserPivot;
use Ulams\Consultations\Rules\UserTermExist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ChangeTermConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(ConsultationsPermissionsEnum::CONSULTATION_CHANGE_TERM, ConsultationUserPivot::class);
    }

    public function rules(): array
    {
        return [
            'term' => ['required', 'date', new UserTermExist(request('consultationTermId') ? (int) request('consultationTermId') : null)],
            'executed_at' => ['required', 'date', 'after_or_equal:now'],
            'user_id' => ['nullable', 'exists:users,id'],
            'accept' => ['boolean'],
        ];
    }
}
