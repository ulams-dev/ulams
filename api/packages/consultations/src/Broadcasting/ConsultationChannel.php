<?php

namespace EscolaLms\Consultations\Broadcasting;

use EscolaLms\Consultations\Models\Consultation;
use Illuminate\Contracts\Auth\Authenticatable;

class ConsultationChannel
{
    public function join(Authenticatable $user, Consultation $consultation, string $term)
    {
        return $consultation->teachers()->where('users.id', '=', $user->getKey())->exists();
    }
}
