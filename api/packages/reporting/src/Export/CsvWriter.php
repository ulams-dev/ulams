<?php

namespace Ulams\Reporting\Export;

/**
 * CSV for spreadsheets: UTF-8 with a BOM, and a text cell that a spreadsheet could read as a formula (it starts with
 * `=`, `+`, `-`, `@`, a tab or a carriage return) gets a leading apostrophe. Numbers are written as numbers.
 */
class CsvWriter
{
    /**
     * @param resource $stream
     * @param list<string> $columns
     */
    public function header($stream, array $columns): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, array_map(fn (string $c) => $this->cell($c), $columns), ',', '"', '');
    }

    /**
     * @param resource $stream
     * @param list<string> $columns
     * @param array<string,mixed> $row
     */
    public function row($stream, array $columns, array $row): void
    {
        fputcsv($stream, array_map(fn (string $c) => $this->cell($row[$c] ?? null), $columns), ',', '"', '');
    }

    public function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        $text = (string) $value;

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $text : $text;
    }
}
