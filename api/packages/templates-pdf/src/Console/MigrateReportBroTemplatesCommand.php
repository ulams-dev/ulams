<?php

namespace Ulams\TemplatesPdf\Console;

use Illuminate\Console\Command;
use Ulams\TemplatesPdf\Pdfme\ReportBroMigrator;

class MigrateReportBroTemplatesCommand extends Command
{
    protected $signature = 'templates-pdf:migrate-reportbro
        {--dry-run : List what would change without writing}
        {--reset : Replace ReportBro templates with the default pdfme template of their event instead of converting them}';

    protected $description = 'Convert stored ReportBro PDF templates and issued PDFs to pdfme (originals are backed up to storage/app/reportbro-legacy)';

    public function handle(ReportBroMigrator $migrator): int
    {
        $report = $migrator->migrate((bool) $this->option('dry-run'), (bool) $this->option('reset'));

        if (empty($report['templates']) && $report['pdfs'] === 0) {
            $this->info('No ReportBro templates or PDFs found.');
            return self::SUCCESS;
        }

        foreach ($report['templates'] as $template) {
            $this->line(sprintf(
                '#%d %s: %s%s',
                $template['id'],
                $template['name'],
                $template['mode'] === 'reset' ? 'replaced with the default pdfme template' : "converted ({$template['fields']} fields)",
                $template['dropped'] ? '; dropped: ' . implode(', ', $template['dropped']) : ''
            ));
        }
        $this->line("Issued PDFs with ReportBro content: {$report['pdfs']}");

        if ($this->option('dry-run')) {
            $this->comment('Dry run: nothing was changed.');
        } else {
            $this->info('Done. Review the templates prefixed "' . ReportBroMigrator::LEGACY_PREFIX . '" in the admin PDF designer.');
            if ($report['backup']) {
                $this->line('Originals: ' . $report['backup']);
            }
        }

        return self::SUCCESS;
    }
}
