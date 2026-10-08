<?php

namespace Ulams\TemplatesPdf\Events;

use Ulams\Core\Models\User;
use Ulams\TemplatesPdf\Models\FabricPDF;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PdfCreated
{
    use Dispatchable, SerializesModels;

    private User $user;
    private FabricPDF $pdf;

    public function __construct(User $user, FabricPDF $pdf)
    {
        $this->user = $user;
        $this->pdf = $pdf;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPdf(): FabricPDF
    {
        return $this->pdf;
    }
}
