<?php

declare(strict_types=1);

namespace Ulams\Compliance\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Compliance\Services\CsvExporter;

class CsvExporterQwenTest extends TestCase
{
    public function testHeaderWritesUtf8BomAndColumns(): void
    {
        $stream = fopen('php://memory', 'w+');
        $csvExporter = new CsvExporter();
        $csvExporter->header($stream, ['Column1', 'Column2']);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        $this->assertEquals("\xEF\xBB\xBFColumn1,Column2\n", $output);
    }

    public function testRowWritesRowWithNeutralizedCells(): void
    {
        $stream = fopen('php://memory', 'w+');
        $csvExporter = new CsvExporter();
        $csvExporter->row($stream, ['Column1', 'Column2'], ['Column1' => 'Value1', 'Column2' => '=Value2']);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        $this->assertEquals("Value1,'=Value2\n", $output);
    }

    public function testCellReturnsEmptyStringForNull(): void
    {
        $csvExporter = new CsvExporter();
        $result = $csvExporter->cell(null);

        $this->assertEquals('', $result);
    }

    public function testCellReturnsStringForBooleanTrue(): void
    {
        $csvExporter = new CsvExporter();
        $result = $csvExporter->cell(true);

        $this->assertEquals('true', $result);
    }

    public function testCellReturnsStringForBooleanFalse(): void
    {
        $csvExporter = new CsvExporter();
        $result = $csvExporter->cell(false);

        $this->assertEquals('false', $result);
    }

    public function testCellNeutralizesCellsStartingWithSpecialCharacters(): void
    {
        $csvExporter = new CsvExporter();
        $result = $csvExporter->cell('=Special');

        $this->assertEquals('\'=Special', $result);
    }
}
