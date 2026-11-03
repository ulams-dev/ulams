<?php

namespace Ulams\Compliance\Services;

/** CSV for spreadsheets: UTF-8 with BOM, and cells that could be read as formulas are neutralised. */
class CsvExporter
{
    /** @param resource $stream */
    public function header($stream, array $columns): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $columns, ',', '"', '');
    }

    /** @param resource $stream */
    public function row($stream, array $columns, array $row): void
    {
        fputcsv($stream, array_map(fn (string $column) => $this->cell($row[$column] ?? null), $columns), ',', '"', '');
    }

    public function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        $text = (string) $value;

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $text : $text;
    }
}
