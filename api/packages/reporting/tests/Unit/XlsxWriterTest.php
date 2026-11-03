<?php

namespace Ulams\Reporting\Tests\Unit;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Ulams\Reporting\Export\XlsxWriter;

/** The streaming XLSX writer, read back with PhpSpreadsheet (a test-only reader) to prove the file is a valid workbook. */
class XlsxWriterTest extends TestCase
{
    /** @param list<list<mixed>> $rows */
    private function workbook(array $headers, array $rows): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $writer = new XlsxWriter($headers);
        foreach ($rows as $row) {
            $writer->row($row);
        }
        $stream = fopen('php://temp', 'w+');
        $writer->finish($stream);
        rewind($stream);
        $path = tempnam(sys_get_temp_dir(), 'xlsx-test-');
        file_put_contents($path, stream_get_contents($stream));
        $book = IOFactory::load($path);
        @unlink($path);

        return $book;
    }

    public function testTypedCellsAreWrittenAndReadBack(): void
    {
        $book = $this->workbook(['Course', 'Learners', 'Cost', 'Done', 'Note'], [
            ['Safety', 12, 2.5, true, null],
            ['=SUM(A1:A2)', 0, -1.25, false, 'multi' . "\n" . 'line & <b>'],
            ['Zażółć 学习', 7, 0.0001, true, ''],
        ]);
        $sheet = $book->getActiveSheet();

        $this->assertSame(['Course', 'Learners', 'Cost', 'Done', 'Note'], $sheet->rangeToArray('A1:E1')[0]);
        $this->assertSame('Safety', $sheet->getCell('A2')->getFormattedValue());
        $this->assertSame(12, $sheet->getCell('B2')->getValue());
        $this->assertEquals(2.5, $sheet->getCell('C2')->getValue());
        $this->assertTrue($sheet->getCell('D2')->getValue());
        $this->assertNull($sheet->getCell('E2')->getValue(), 'a null is no cell');
        $this->assertSame('=SUM(A1:A2)', $sheet->getCell('A3')->getFormattedValue());
        $this->assertSame(DataType::TYPE_INLINE, $sheet->getCell('A3')->getDataType(), 'text that looks like a formula stays text');
        $this->assertFalse($sheet->getCell('D3')->getValue());
        $this->assertSame("multi\nline & <b>", $sheet->getCell('E3')->getFormattedValue());
        $this->assertSame('Zażółć 学习', $sheet->getCell('A4')->getFormattedValue());
        $this->assertSame(4, $sheet->getHighestRow());
    }

    public function testTheHeaderIsBoldAndFrozenAndFiltered(): void
    {
        $book = $this->workbook(['A', 'B'], [['x', 1]]);
        $sheet = $book->getActiveSheet();

        $this->assertTrue($sheet->getStyle('A1')->getFont()->getBold());
        $this->assertFalse($sheet->getStyle('A2')->getFont()->getBold());
        $this->assertSame('A2', $sheet->getFreezePane());
        $this->assertSame('A1:B2', $sheet->getAutoFilter()->getRange());
    }

    public function testControlCharactersXmlCannotCarryAreDropped(): void
    {
        $book = $this->workbook(['T'], [["a\x00b\x07c\x0Bd\x1Fe\td"]]);

        $this->assertSame("abcde\td", $book->getActiveSheet()->getCell('A2')->getFormattedValue());
    }

    public function testColumnLetters(): void
    {
        $this->assertSame(['A', 'Z', 'AA', 'AZ', 'BA', 'ZZ', 'AAA'], array_map([XlsxWriter::class, 'letters'], [1, 26, 27, 52, 53, 702, 703]));
    }

    public function testManyColumnsAndAnEmptyReport(): void
    {
        $headers = array_map(fn (int $i) => "c{$i}", range(1, 30));
        $book = $this->workbook($headers, [array_map(fn (int $i) => $i, range(1, 30))]);

        $this->assertSame(30, $book->getActiveSheet()->getCell('AD2')->getValue());
        $this->assertSame(1, $this->workbook(['only'], [])->getActiveSheet()->getHighestRow());
    }

    public function testASheetHoldsAtMostTheRowsExcelDoes(): void
    {
        $writer = new XlsxWriter(['n']);
        $ref = new \ReflectionProperty($writer, 'row');
        $ref->setValue($writer, XlsxWriter::MAX_ROWS);

        $this->expectException(RuntimeException::class);
        $writer->row([1]);
    }
}
