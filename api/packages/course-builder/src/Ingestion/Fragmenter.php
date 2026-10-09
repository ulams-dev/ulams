<?php

namespace Ulams\CourseBuilder\Ingestion;

/**
 * Splits Markdown into fragments: one or more per section (text under a heading), paragraph
 * groups packed to `min`–`max` tokens (≈ 4 characters per token). Fenced code blocks are never
 * split and headings inside them are ignored. A single leading `#` heading is the document title,
 * not a section. Sections are numbered by their position (`2.3`) for labels like "§2.3 Ratios".
 */
final class Fragmenter
{
    public function __construct(private readonly int $minTokens = 150, private readonly int $maxTokens = 600)
    {
    }

    public static function tokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * @param array<int,array{0:int,1:int}> $pageMarks [char offset, page number], ascending
     * @return array{title:?string,fragments:array<int,array<string,mixed>>,sections:int}
     */
    public function split(string $markdown, array $pageMarks = []): array
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $offsets = [];
        $pos = 0;
        foreach ($lines as $i => $line) {
            $offsets[$i] = $pos;
            $pos += strlen($line) + 1;
        }

        // pass 1: headings outside fences
        $headings = [];
        $fence = null;
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line, $m)) {
                $marker = $m[1][0];
                if ($fence === null) {
                    $fence = $marker;
                } elseif ($fence === $marker) {
                    $fence = null;
                }
                continue;
            }
            if ($fence === null && preg_match('/^\s{0,3}(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $m)) {
                $headings[] = ['line' => $i, 'level' => strlen($m[1]), 'title' => self::plain($m[2])];
            }
        }

        $title = null;
        $h1 = array_values(array_filter($headings, fn ($h) => $h['level'] === 1));
        if (count($h1) === 1 && $headings !== [] && $headings[0]['level'] === 1) {
            $title = $h1[0]['title'];
            $titleLine = $headings[0]['line'];
            array_shift($headings);
        }

        $topLevel = $headings === [] ? 1 : min(array_column($headings, 'level'));
        $counters = [];
        $path = [];
        $sections = [];
        $start = isset($titleLine) ? $titleLine + 1 : 0;
        // preamble before the first section
        $sections[] = ['path' => [], 'section' => null, 'level' => 0, 'from' => $start, 'to' => ($headings[0]['line'] ?? count($lines)) - 1];
        foreach ($headings as $k => $h) {
            $depth = max(0, $h['level'] - $topLevel);
            $counters = array_slice($counters, 0, $depth + 1);
            for ($d = 0; $d < $depth; $d++) {
                $counters[$d] ??= 0;
            }
            $counters[$depth] = ($counters[$depth] ?? 0) + 1;
            $path = array_slice($path, 0, $depth);
            for ($d = 0; $d < $depth; $d++) {
                $path[$d] ??= '';
            }
            $path[$depth] = $h['title'];
            $sections[] = [
                'path' => array_values($path),
                'section' => implode('.', array_map(fn ($c) => max(1, (int) $c), array_slice($counters, 0, $depth + 1))),
                'level' => $depth + 1,
                'from' => $h['line'] + 1,
                'to' => ($headings[$k + 1]['line'] ?? count($lines)) - 1,
            ];
        }

        $fragments = [];
        foreach ($sections as $section) {
            $blocks = $this->blocks($lines, $offsets, $section['from'], $section['to']);
            $ordinal = 0;
            foreach ($this->pack($blocks) as $chunk) {
                $text = trim(implode("\n\n", array_column($chunk, 'text')));
                if ($text === '') {
                    continue;
                }
                $charStart = $chunk[0]['start'];
                $charEnd = end($chunk)['end'];
                $fragments[] = [
                    'heading_path' => $section['path'],
                    'section' => $section['section'],
                    'level' => $section['level'],
                    'ordinal' => $ordinal++,
                    'text' => $text,
                    'char_start' => $charStart,
                    'char_end' => $charEnd,
                    'page_start' => self::pageAt($pageMarks, $charStart),
                    'page_end' => self::pageAt($pageMarks, max($charStart, $charEnd - 1)),
                    'token_estimate' => self::tokens($text),
                ];
            }
        }

        return ['title' => $title, 'fragments' => $fragments, 'sections' => count($headings)];
    }

    /** @return array<int,array{text:string,start:int,end:int}> paragraphs and whole code blocks */
    private function blocks(array $lines, array $offsets, int $from, int $to): array
    {
        $blocks = [];
        $current = [];
        $startLine = null;
        $fence = null;
        $flush = function (int $endLine) use (&$blocks, &$current, &$startLine, $lines, $offsets) {
            if ($current !== [] && trim(implode('', $current)) !== '') {
                $blocks[] = [
                    'text' => rtrim(implode("\n", $current)),
                    'start' => $offsets[$startLine],
                    'end' => $offsets[$endLine] + strlen($lines[$endLine]),
                ];
            }
            $current = [];
            $startLine = null;
        };
        for ($i = $from; $i <= $to && $i < count($lines); $i++) {
            $line = $lines[$i];
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line, $m)) {
                $fence = $fence === null ? $m[1][0] : ($fence === $m[1][0] ? null : $fence);
            }
            if ($fence === null && trim($line) === '' && !preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line)) {
                if ($current !== []) {
                    $flush($i - 1);
                }
                continue;
            }
            $startLine ??= $i;
            $current[] = $line;
        }
        if ($current !== []) {
            $flush(min($to, count($lines) - 1));
        }

        return $blocks;
    }

    /** @return array<int,array<int,array{text:string,start:int,end:int}>> */
    private function pack(array $blocks): array
    {
        $chunks = [];
        $chunk = [];
        $tokens = 0;
        foreach ($blocks as $block) {
            $t = self::tokens($block['text']);
            if ($chunk !== [] && $tokens >= $this->minTokens && $tokens + $t > $this->maxTokens) {
                $chunks[] = $chunk;
                $chunk = [];
                $tokens = 0;
            }
            $chunk[] = $block;
            $tokens += $t;
            if ($tokens >= $this->maxTokens) {
                $chunks[] = $chunk;
                $chunk = [];
                $tokens = 0;
            }
        }
        if ($chunk !== []) {
            // a short tail joins the previous chunk of the same section
            if ($tokens < $this->minTokens && $chunks !== [] && self::tokens(implode('', array_column(end($chunks), 'text'))) + $tokens <= $this->maxTokens) {
                $chunks[count($chunks) - 1] = array_merge($chunks[count($chunks) - 1], $chunk);
            } else {
                $chunks[] = $chunk;
            }
        }

        return $chunks;
    }

    private static function pageAt(array $marks, int $offset): ?int
    {
        $page = null;
        foreach ($marks as [$at, $number]) {
            if ($at > $offset) {
                break;
            }
            $page = $number;
        }

        return $page;
    }

    /** Heading text without inline Markdown. */
    public static function plain(string $text): string
    {
        $text = preg_replace('/!\[([^\]]*)\]\([^)]*\)/', '$1', $text);
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', (string) $text);

        return trim((string) preg_replace('/[*_`~]+/', '', (string) $text));
    }
}
