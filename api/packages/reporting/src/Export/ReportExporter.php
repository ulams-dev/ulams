<?php

namespace Ulams\Reporting\Export;

use Ulams\Reporting\Services\ExportLog;
use Ulams\Reporting\Services\ReportRunner;
use Ulams\Reporting\Services\ReportingSettings;
use Ulams\Reporting\Support\ReportContext;

/**
 * Writes every row of a report to a stream as CSV or JSON lines (capped at `reporting.export_rows`) and records the
 * export: who, which dataset, what was asked, which saved report, by which route, how many rows. A withheld group is a
 * row like any other, marked `_withheld` in JSON lines, so a file can never carry more than the screen does.
 */
class ReportExporter
{
    public const FORMATS = ['csv', 'jsonl'];

    public function __construct(
        private readonly ReportRunner $runner,
        private readonly CsvWriter $csv,
        private readonly ExportLog $log,
        private readonly ReportingSettings $settings,
    ) {
    }

    /**
     * @param array<string,mixed> $definition
     * @param resource $stream
     * @return int rows written
     */
    public function write(array $definition, ReportContext $context, string $format, $stream, ?string $savedReportId = null, string $channel = 'download'): int
    {
        $result = $this->runner->stream($definition, $context, $this->settings->exportRows());
        $names = array_column($result['columns'], 'name');
        $count = 0;

        if ($format === 'csv') {
            $this->csv->header($stream, $names);
        }
        foreach ($result['rows'] as $row) {
            if ($format === 'csv') {
                $this->csv->row($stream, $names, $row);
            } else {
                $line = array_intersect_key($row, array_flip([...$names, '_withheld']));
                fwrite($stream, json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            }
            $count++;
        }

        $this->log->export($context->viewer?->getAuthIdentifier(), $result['dataset'], $definition, $format, $count, $savedReportId, $channel);

        return $count;
    }
}
