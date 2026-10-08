<?php

namespace EscolaLms\Consultations\Events;

use EscolaLms\Consultations\Models\ConsultationUserTerm;
use EscolaLms\Core\Models\User;
use EscolaLms\Consultations\Models\ConsultationUserPivot;
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
