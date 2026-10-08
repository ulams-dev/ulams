<?php

namespace EscolaLms\CsvUsers\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EscolaLmsImportedNewUserTemplateEvent
{
    use Dispatchable, SerializesModels;

    private Authenticatable $user;
    private string $returnUrl;

    public function __construct(Authenticatable $user, string $returnUrl)
    {
        $this->user = $user;
        $this->returnUrl = $returnUrl;
    }

    public function getUser(): Authenticatable
    {
        return $this->user;
    }

    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }
}
