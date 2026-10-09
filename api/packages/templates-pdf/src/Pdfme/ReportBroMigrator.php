<?php

namespace Ulams\TemplatesPdf\Pdfme;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Ulams\Templates\Facades\Template as TemplateFacade;
use Ulams\TemplatesPdf\Core\PdfChannel;

/**
 * Moves stored ReportBro reports to pdfme (see ReportBroConverter):
 * - PDF templates (channel PdfChannel, section `content`): converted, or
 *   replaced by the default pdfme template of their event (with `reset`, and
 *   always for the auto-created "Default template for event ..." templates);
 *   the name is prefixed with LEGACY_PREFIX so admins know to review them;
 * - issued PDFs (`fabric_pdfs.content`, variables already filled in): converted
 *   so they can still be downloaded.
 * The original JSON of everything changed is written to
 * storage/app/reportbro-legacy/<timestamp>.jsonl first.
 *
 * Works on the query builder (no model events), so it is safe in migrations.
 */
class ReportBroMigrator
{
    public const LEGACY_PREFIX = '[Converted from ReportBro] ';
    public const BACKUP_DIRECTORY = 'reportbro-legacy';
    /** Name given by Template::createDefaultTemplatesForChannel(). */
    public const SHIPPED_DEFAULT_PREFIX = 'Default template for event ';

    private ?string $backupPath = null;

    public function __construct(private ReportBroConverter $converter = new ReportBroConverter())
    {
    }

    /**
     * @return array{templates: array<int, array>, pdfs: int, backup: ?string}
     */
    public function migrate(bool $dryRun = false, bool $reset = false): array
    {
        $report = ['templates' => [], 'pdfs' => 0, 'backup' => null];
        if (!Schema::hasTable('templates') || !Schema::hasTable('template_sections')) {
            return $report;
        }

        $sections = DB::table('template_sections')
            ->join('templates', 'templates.id', '=', 'template_sections.template_id')
            ->where('templates.channel', PdfChannel::class)
            ->where('template_sections.key', 'content')
            ->select('template_sections.id as section_id', 'template_sections.content', 'templates.id as template_id', 'templates.name', 'templates.event')
            ->orderBy('template_sections.id')
            ->get();

        foreach ($sections as $row) {
            $reportBro = $this->decodeReportBro($row->content);
            if ($reportBro === null) {
                continue;
            }

            // Auto-created default templates get the new default certificate.
            $isShippedDefault = str_starts_with((string) $row->name, self::SHIPPED_DEFAULT_PREFIX);
            $default = $reset || $isShippedDefault ? $this->defaultContent((string) $row->event) : null;
            $result = $default === null ? $this->converter->convert($reportBro) : null;
            $content = $default ?? json_encode($result['template'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $report['templates'][] = [
                'id' => $row->template_id,
                'name' => $row->name,
                'mode' => $default === null ? 'converted' : 'reset',
                'fields' => $result['converted'] ?? null,
                'dropped' => $result['dropped'] ?? [],
            ];

            if ($dryRun) {
                continue;
            }
            $this->backup(['type' => 'template', 'template_id' => $row->template_id, 'name' => $row->name, 'content' => $row->content]);
            DB::table('template_sections')->where('id', $row->section_id)->update(['content' => $content, 'updated_at' => now()]);
            if (!str_starts_with((string) $row->name, self::LEGACY_PREFIX)) {
                DB::table('templates')->where('id', $row->template_id)->update(['name' => self::LEGACY_PREFIX . $row->name, 'updated_at' => now()]);
            }
        }

        if (Schema::hasTable('fabric_pdfs')) {
            DB::table('fabric_pdfs')->select('id', 'content')->orderBy('id')->chunkById(200, function ($rows) use (&$report, $dryRun) {
                foreach ($rows as $row) {
                    $reportBro = $this->decodeReportBro($row->content);
                    if ($reportBro === null) {
                        continue;
                    }
                    $report['pdfs']++;
                    if ($dryRun) {
                        continue;
                    }
                    $this->backup(['type' => 'pdf', 'pdf_id' => $row->id, 'content' => $row->content]);
                    $template = $this->converter->convert($reportBro)['template'];
                    // `content` is cast to array on the model: store a JSON-encoded JSON string, as the model does
                    $json = json_encode($template, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    DB::table('fabric_pdfs')->where('id', $row->id)->update(['content' => json_encode($json), 'path' => null, 'updated_at' => now()]);
                }
            });
        }

        $report['backup'] = $this->backupPath;

        return $report;
    }

    private function decodeReportBro(mixed $content): ?array
    {
        if (!is_string($content) || !str_contains($content, 'docElements')) {
            return null;
        }
        try {
            $decoded = PdfmeTemplate::decode($content);
        } catch (\Throwable) {
            return null;
        }

        return PdfmeTemplate::isReportBro($decoded) ? $decoded : null;
    }

    private function defaultContent(string $event): ?string
    {
        $variableClass = TemplateFacade::getVariableClassName($event, PdfChannel::class);

        return $variableClass ? ($variableClass::defaultSectionsContent()['content'] ?? null) : null;
    }

    /**
     * Written under storage/app directly, not through the `local` disk, which
     * some installations point at the public storage directory.
     */
    private function backup(array $entry): void
    {
        $this->backupPath ??= storage_path('app/' . self::BACKUP_DIRECTORY . '/' . now()->format('Ymd_His_u') . '.jsonl');
        File::ensureDirectoryExists(dirname($this->backupPath), 0700);
        File::append($this->backupPath, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }
}
