<?php

namespace Ulams\Reporting\Export;

use RuntimeException;
use XMLWriter;
use ZipArchive;

/**
 * A streaming writer for one-sheet XLSX files (Office Open XML), with PHP's own `ext-zip` and `XMLWriter` and no
 * further dependency. PhpSpreadsheet, which the application also carries for its other exports, holds every cell in
 * memory, which does not suit a report of a million rows; here the sheet goes to a temporary file row by row.
 *
 * Text is written as inline strings (never as formulas, so a cell starting with `=` stays text), numbers and booleans as
 * typed cells, empty values as no cell. The header row is bold and frozen. Excel holds at most 1 048 576 rows.
 */
class XlsxWriter
{
    public const MAX_ROWS = 1048576;

    private XMLWriter $sheet;
    private string $sheetPath;
    private int $row = 0;
    private int $columns = 0;

    /** @param list<string> $headers */
    public function __construct(array $headers)
    {
        $this->sheetPath = $this->temporaryFile();
        $this->columns = count($headers);
        $this->sheet = new XMLWriter();
        $this->sheet->openUri($this->sheetPath);
        $this->sheet->startDocument('1.0', 'UTF-8', 'yes');
        $this->sheet->startElement('worksheet');
        $this->sheet->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $this->sheet->startElement('sheetViews');
        $this->sheet->startElement('sheetView');
        $this->sheet->writeAttribute('workbookViewId', '0');
        $this->sheet->startElement('pane');
        $this->sheet->writeAttribute('ySplit', '1');
        $this->sheet->writeAttribute('topLeftCell', 'A2');
        $this->sheet->writeAttribute('activePane', 'bottomLeft');
        $this->sheet->writeAttribute('state', 'frozen');
        $this->sheet->endElement(); // pane
        $this->sheet->endElement(); // sheetView
        $this->sheet->endElement(); // sheetViews
        $this->sheet->startElement('sheetData');
        $this->write($headers, true);
    }

    /** @param list<mixed> $values one value per column: string, int, float, bool or null */
    public function row(array $values): void
    {
        if ($this->row >= self::MAX_ROWS) {
            throw new RuntimeException('An XLSX sheet holds at most ' . self::MAX_ROWS . ' rows.');
        }
        $this->write($values, false);
    }

    /** Rows written so far, the header included. */
    public function rows(): int
    {
        return $this->row;
    }

    /** Finishes the file and copies it to `$stream`. @param resource $stream */
    public function finish($stream): void
    {
        $this->sheet->endElement(); // sheetData
        if ($this->columns > 0 && $this->row > 0) {
            $this->sheet->startElement('autoFilter');
            $this->sheet->writeAttribute('ref', 'A1:' . self::letters($this->columns) . $this->row);
            $this->sheet->endElement();
        }
        $this->sheet->endElement(); // worksheet
        $this->sheet->endDocument();
        $this->sheet->flush();

        $zipPath = $this->temporaryFile();
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create the XLSX file.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>');
        $zip->addFile($this->sheetPath, 'xl/worksheets/sheet1.xml');
        $zip->close();

        $in = fopen($zipPath, 'rb');
        stream_copy_to_stream($in, $stream);
        fclose($in);
        @unlink($zipPath);
        @unlink($this->sheetPath);
    }

    /** @param list<mixed> $values */
    private function write(array $values, bool $header): void
    {
        $this->row++;
        $this->sheet->startElement('row');
        $this->sheet->writeAttribute('r', (string) $this->row);
        foreach (array_values($values) as $i => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $this->sheet->startElement('c');
            $this->sheet->writeAttribute('r', self::letters($i + 1) . $this->row);
            if ($header) {
                $this->sheet->writeAttribute('s', '1');
            }
            if (is_bool($value)) {
                $this->sheet->writeAttribute('t', 'b');
                $this->sheet->writeElement('v', $value ? '1' : '0');
            } elseif (is_int($value) || (is_float($value) && is_finite($value))) {
                $this->sheet->writeElement('v', (string) $value);
            } else {
                $this->sheet->writeAttribute('t', 'inlineStr');
                $this->sheet->startElement('is');
                $this->sheet->startElement('t');
                $this->sheet->writeAttribute('xml:space', 'preserve');
                $this->sheet->text(self::clean((string) $value));
                $this->sheet->endElement();
                $this->sheet->endElement();
            }
            $this->sheet->endElement(); // c
        }
        $this->sheet->endElement(); // row
        if ($this->row % 1000 === 0) {
            $this->sheet->flush();
        }
    }

    /** Column letters of a 1-based column number: 1 is A, 27 is AA. */
    public static function letters(int $column): string
    {
        $letters = '';
        for ($n = $column; $n > 0; $n = intdiv($n - 1, 26)) {
            $letters = chr(65 + (($n - 1) % 26)) . $letters;
        }

        return $letters;
    }

    /** Characters XML 1.0 cannot carry (control codes other than tab, line feed and carriage return) are dropped. */
    private static function clean(string $text): string
    {
        return (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);
    }

    private function temporaryFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ulams-xlsx-');
        if ($path === false) {
            throw new RuntimeException('Cannot create a temporary file.');
        }

        return $path;
    }
}
