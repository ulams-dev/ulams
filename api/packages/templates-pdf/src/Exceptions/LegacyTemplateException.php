<?php

namespace Ulams\TemplatesPdf\Exceptions;

/**
 * The template is a ReportBro report (the designer used before pdfme). It has
 * to be converted or re-created in the pdfme designer before it can be
 * rendered; see `php artisan templates-pdf:migrate-reportbro`.
 */
class LegacyTemplateException extends PdfRenderException
{
    public function __construct(string $message = 'This PDF template was made with ReportBro, which is no longer supported. Re-create it in the PDF designer or run templates-pdf:migrate-reportbro.')
    {
        parent::__construct($message, 422, 'legacy_template');
    }
}
