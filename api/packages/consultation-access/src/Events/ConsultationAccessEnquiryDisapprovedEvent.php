<?php

namespace EscolaLms\ConsultationAccess\Events;

use EscolaLms\Auth\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConsultationAccessEnquiryDisapprovedEvent
{
    use Dispatchable, SerializesModels;

    private User $user;
    private string $consultationName;
    private ?string $message;

    public function __construct(User $user, string $consultationName, ?string $message)
    {
        $this->user = $user;
        $this->consultationName = $consultationName;
        $this->message = $message;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getConsultationName(): string
    {
        return $this->consultationName;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }
}
