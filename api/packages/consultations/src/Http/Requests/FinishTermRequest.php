<?php

namespace EscolaLms\Consultations\Http\Requests;

use EscolaLms\Consultations\Enum\ConsultationsPermissionsEnum;
use EscolaLms\Consultations\Models\ConsultationUserPivot;
use Illuminate\Support\Facades\Gate;

class FinishTermRequest extends ConsultationUserTermRequest
{
    public function authorize(): bool
    {
        return Gate::allows(ConsultationsPermissionsEnum::CONSULTATION_CHANGE_TERM, ConsultationUserPivot::class);
    }
}
