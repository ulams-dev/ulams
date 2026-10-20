<?php

namespace Ulams\TemplatesPdf\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ulams\TemplatesPdf\Models\FabricPDF;

class CertificateRevoked
{
    use Dispatchable, SerializesModels;

    public function __construct(public FabricPDF $pdf)
    {
    }
}
