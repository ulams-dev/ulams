<?php

namespace Ulams\Consultations\Events;

use Ulams\Consultations\Models\ConsultationUserTerm;
use Ulams\Core\Models\User;
use Ulams\Consultations\Models\ConsultationUserPivot;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class ConsultationTerm
{
    use Dispatchable, SerializesModels;

    private User $user;
    private ConsultationUserPivot $consultationTerm;
    private ?ConsultationUserTerm $consultationUserTerm;

    public function __construct(User $user, ConsultationUserPivot $consultationTerm, ?ConsultationUserTerm $consultationUserTerm = null)
    {
        $this->user = $user;
        $this->consultationTerm = $consultationTerm;
        $this->consultationUserTerm = $consultationUserTerm;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getConsultationTerm(): ConsultationUserPivot
    {
        return $this->consultationTerm;
    }

    public function getConsultationUserTerm(): ConsultationUserTerm
    {
        return $this->consultationUserTerm;
    }
}
