<?php

use Illuminate\Database\Migrations\Migration;
use Ulams\TemplatesPdf\Pdfme\ReportBroMigrator;

/**
 * ReportBro was replaced by pdfme: convert the stored ReportBro templates and
 * issued PDFs (best effort, originals backed up to storage/app/reportbro-legacy).
 * Same as `php artisan templates-pdf:migrate-reportbro`.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(ReportBroMigrator::class)->migrate();
    }

    public function down(): void
    {
        // The originals are in storage/app/reportbro-legacy/*.jsonl; ReportBro itself is gone.
    }
};
