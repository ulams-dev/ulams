<?php

namespace Ulams\CourseBuilder\Ingestion;

use RuntimeException;
use Smalot\PdfParser\Parser;

/**
 * PDF → Markdown with page numbers (smalot/pdfparser, already in the lock file). Headings come
 * from numbering ("2.3 Brewing ratios") and short all-caps lines. When the extraction looks poor
 * (little text per page), the result is flagged `pdf_native` so the outline step can also send
 * the original PDF as a native document block.
 */
final class PdfConverter
{
    /** @return array{markdown:string,page_marks:array<int,array{0:int,1:int}>,pages:int,pdf_native:bool,title:?string} */
    public function convert(string $path, int $maxPages): array
    {
        try {
            $pdf = (new Parser())->parseFile($path);
        } catch (\Throwable $e) {
            throw new RuntimeException('The PDF could not be read: ' . $e->getMessage(), 0, $e);
        }
        $pages = $pdf->getPages();
        if (count($pages) > $maxPages) {
            throw new RuntimeException(sprintf('The PDF has %d pages; the limit is %d.', count($pages), $maxPages));
        }

        $markdown = '';
        $marks = [];
        $chars = 0;
        $title = trim((string) ($pdf->getDetails()['Title'] ?? '')) ?: null;
        foreach ($pages as $index => $page) {
            $marks[] = [strlen($markdown), $index + 1];
            $text = str_replace(["\r\n", "\r", "\f"], "\n", (string) $page->getText());
            $chars += strlen(preg_replace('/\s+/', '', $text) ?? '');
            $paragraph = [];
            foreach (explode("\n", $text) as $line) {
                $line = trim(preg_replace('/[ \t]+/', ' ', $line) ?? '');
                if ($line === '') {
                    if ($paragraph !== []) {
                        $markdown .= implode(' ', $paragraph) . "\n\n";
                        $paragraph = [];
                    }
                    continue;
                }
                $heading = self::heading($line);
                if ($heading !== null) {
                    if ($paragraph !== []) {
                        $markdown .= implode(' ', $paragraph) . "\n\n";
                        $paragraph = [];
                    }
                    $markdown .= str_repeat('#', $heading[0] + 1) . ' ' . $heading[1] . "\n\n";
                    continue;
                }
                if (preg_match('/^(?:[•\-–*]|\d+[.)])\s+/u', $line)) {
                    if ($paragraph !== []) {
                        $markdown .= implode(' ', $paragraph) . "\n";
                        $paragraph = [];
                    }
                    $markdown .= '- ' . preg_replace('/^(?:[•\-–*]|\d+[.)])\s+/u', '', $line) . "\n";
                    continue;
                }
                $paragraph[] = $line;
            }
            if ($paragraph !== []) {
                $markdown .= implode(' ', $paragraph) . "\n\n";
            }
        }
        $pageCount = max(1, count($pages));

        return [
            'markdown' => trim($markdown) . "\n",
            'page_marks' => $marks,
            'pages' => count($pages),
            'pdf_native' => $chars / $pageCount < 200,
            'title' => $title,
        ];
    }

    /** @return array{0:int,1:string}|null [level, title] */
    public static function heading(string $line): ?array
    {
        if (mb_strlen($line) > 90 || preg_match('/[.:;,]$/', $line)) {
            return null;
        }
        if (preg_match('/^(\d{1,2}(?:\.\d{1,2}){0,3})\.?\s+(\p{Lu}.{1,80})$/u', $line, $m) && !preg_match('/^\d+(\.\d+)?\s*(%|ml|g|°|kg|mm|cm)/u', $line)) {
            return [substr_count($m[1], '.') + 1, $m[2]];
        }
        if (preg_match('/^[\p{Lu}0-9 &\-:\'’]{4,60}$/u', $line) && preg_match('/\p{Lu}{3}/u', $line)) {
            return [1, mb_convert_case(mb_strtolower($line), MB_CASE_TITLE)];
        }

        return null;
    }
}
