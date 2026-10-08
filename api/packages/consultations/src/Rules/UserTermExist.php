<?php

namespace EscolaLms\Consultations\Rules;

use EscolaLms\Consultations\Models\ConsultationUserTerm;
use Illuminate\Contracts\Validation\Rule;

class UserTermExist implements Rule
{
    private ?int $consultationUserId;

    public function __construct(?int $consultationUserId = null)
    {
        $this->consultationUserId = $consultationUserId;
    }

    public function passes($attribute, $value)
    {
        if (!is_numeric($this->consultationUserId)) {
            return false;
        }

        return ConsultationUserTerm::query()
            ->where('consultation_user_id', '=', $this->consultationUserId)
            ->where('executed_at', '=', $value)
            ->exists();
    }

    public function message()
    {
        return __('The consultation user term not found');
    }
}
