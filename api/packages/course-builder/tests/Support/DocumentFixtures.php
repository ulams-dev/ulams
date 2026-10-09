<?php

namespace Ulams\CourseBuilder\Tests\Support;

use ZipArchive;

/**
 * Builds DOCX and PDF test documents without extra libraries: a DOCX with headings, a list, a
 * table, monospace code and a hyperlink; a text PDF with numbered headings on several pages.
 */
final class DocumentFixtures
{
    private const W = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';

    /**
     * @param array<int,array{0:string,1:string}> $paragraphs [kind, text]; kinds: h1, h2, h3, p, li, code, link, table (text "a|b;c|d")
     */
    public static function docx(string $path, array $paragraphs, string $title = 'Fixture'): string
    {
        $body = '';
        foreach ($paragraphs as [$kind, $text]) {
            $t = htmlspecialchars($text, ENT_XML1);
            $body .= match ($kind) {
                'h1', 'h2', 'h3' => '<w:p><w:pPr><w:pStyle w:val="Heading' . substr($kind, 1) . '"/></w:pPr><w:r><w:t>' . $t . '</w:t></w:r></w:p>',
                'li' => '<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>' . $t . '</w:t></w:r></w:p>',
                'code' => '<w:p><w:r><w:rPr><w:rFonts w:ascii="Courier New" w:hAnsi="Courier New"/></w:rPr><w:t xml:space="preserve">' . $t . '</w:t></w:r></w:p>',
                'link' => '<w:p><w:r><w:t xml:space="preserve">See </w:t></w:r><w:hyperlink r:id="rId9"><w:r><w:t>' . $t . '</w:t></w:r></w:hyperlink></w:p>',
                'table' => self::table($text),
                'bold' => '<w:p><w:r><w:rPr><w:b/></w:rPr><w:t>' . $t . '</w:t></w:r><w:r><w:t xml:space="preserve"> follows.</w:t></w:r></w:p>',
                default => '<w:p><w:r><w:t xml:space="preserve">' . $t . '</w:t></w:r></w:p>',
            };
        }
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles ' . self::W . '>'
            . '<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Heading3"><w:name w:val="heading 3"/></w:style></w:styles>';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId9" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.org/docs" TargetMode="External"/></Relationships>');
        $zip->addFromString('word/styles.xml', $styles);
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>' . htmlspecialchars($title, ENT_XML1) . '</dc:title></cp:coreProperties>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document ' . self::W . '><w:body>' . $body . '</w:body></w:document>');
        $zip->close();

        return $path;
    }

    private static function table(string $spec): string
    {
        $rows = '';
        foreach (explode(';', $spec) as $row) {
            $cells = '';
            foreach (explode('|', $row) as $cell) {
                $cells .= '<w:tc><w:p><w:r><w:t>' . htmlspecialchars($cell, ENT_XML1) . '</w:t></w:r></w:p></w:tc>';
            }
            $rows .= '<w:tr>' . $cells . '</w:tr>';
        }

        return '<w:tbl>' . $rows . '</w:tbl>';
    }

    /** @param array<int,string[]> $pages lines per page */
    public static function pdf(string $path, array $pages, string $title = 'Fixture'): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $n = 4;
        $pageObjects = [];
        foreach ($pages as $lines) {
            $stream = "BT /F1 11 Tf 14 TL 56 780 Td\n";
            foreach ($lines as $line) {
                $stream .= '(' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line) . ") Tj T*\n";
            }
            $stream .= 'ET';
            $pageId = $n++;
            $contentId = $n++;
            $kids[] = "{$pageId} 0 R";
            $pageObjects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentId} 0 R >>";
            $pageObjects[$contentId] = '<< /Length ' . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects += $pageObjects;
        $infoId = $n;
        $objects[$infoId] = '<< /Title (' . $title . ') >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($objects as $id => $_) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R /Info {$infoId} 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
        file_put_contents($path, $pdf);

        return $path;
    }
}
