<?php

namespace EscolaLms\ConsultationAccess\Events;

use EscolaLms\Auth\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;

class ConsultationAccessEnquiryEvent
{
    use Dispatchable, SerializesModels;

    private User $user;
    private ConsultationAccessEnquiry $consultationAccessEnquiry;

    public function __construct(User $user, ConsultationAccessEnquiry $consultationAccessEnquiry)
    {
        $this->user = $user;
        $this->consultationAccessEnquiry = $consultationAccessEnquiry;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getConsultationAccessEnquiry(): ConsultationAccessEnquiry
    {
        return $this->consultationAccessEnquiry;
    }
}
