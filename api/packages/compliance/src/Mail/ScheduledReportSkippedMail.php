<?php

namespace Ulams\Compliance\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Tells the owner of a scheduled saved report who did not get this run, and why. */
class ScheduledReportSkippedMail extends Mailable
{
    /** @param list<array{name:string,reason:string}> $skipped */
    public function __construct(public string $reportName, public array $skipped)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Scheduled report not sent to everyone: :name', ['name' => $this->reportName]));
    }

    public function content(): Content
    {
        return new Content(text: 'ulams-compliance::report-skipped-mail');
    }
}
