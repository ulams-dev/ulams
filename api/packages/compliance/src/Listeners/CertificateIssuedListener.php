<?php

namespace Ulams\Compliance\Listeners;

use Ulams\Compliance\Services\CompletionService;
use Ulams\TemplatesPdf\Events\PdfCreated;

class CertificateIssuedListener
{
    public function __construct(private CompletionService $completion)
    {
    }

    public function handle(PdfCreated $event): void
    {
        $this->completion->certificateIssued($event->getPdf());
    }
}
