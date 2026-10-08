<?php

namespace EscolaLms\Consultations\Events;

use EscolaLms\Consultations\Models\ConsultationUserPivot;
use EscolaLms\Consultations\Models\ConsultationUserTerm;
use EscolaLms\Core\Models\User;

class ReminderTrainerAboutTerm extends ConsultationTerm
{
    private string $status;

    public function __construct(User $user, ConsultationUserPivot $consultationTerm, string $status, ?ConsultationUserTerm $consultationUserTerm = null)
    {
        parent::__construct($user, $consultationTerm, $consultationUserTerm);
        $this->status = $status;
    }

    public function getStatus()
    {
        return $this->status;
    }
}
