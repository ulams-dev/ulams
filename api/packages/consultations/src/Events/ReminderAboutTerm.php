<?php

namespace Ulams\Consultations\Events;

use Ulams\Consultations\Models\ConsultationUserPivot;
use Ulams\Consultations\Models\ConsultationUserTerm;
use Ulams\Core\Models\User;

class ReminderAboutTerm extends ConsultationTerm
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
