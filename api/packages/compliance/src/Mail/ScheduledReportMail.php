<?php

namespace Ulams\Compliance\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A scheduled compliance report: the dataset as an attachment, with a plain text note. */
class ScheduledReportMail extends Mailable
{
    public function __construct(public string $reportName, public string $fileName, public string $contents, public string $mime, public int $rows)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Compliance report: :name', ['name' => $this->reportName]));
    }

    public function content(): Content
    {
        return new Content(text: 'ulams-compliance::report-mail');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->contents, $this->fileName)->withMime($this->mime)];
    }
}
