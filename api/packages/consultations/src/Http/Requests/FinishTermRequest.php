<?php

namespace Ulams\Consultations\Http\Requests;

use Ulams\Consultations\Enum\ConsultationsPermissionsEnum;
use Ulams\Consultations\Models\ConsultationUserPivot;
use Illuminate\Support\Facades\Gate;

class FinishTermRequest extends ConsultationUserTermRequest
{
    public function authorize(): bool
    {
        return Gate::allows(ConsultationsPermissionsEnum::CONSULTATION_CHANGE_TERM, ConsultationUserPivot::class);
    }
}
