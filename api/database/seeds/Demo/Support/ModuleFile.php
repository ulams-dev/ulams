<?php

namespace Database\Seeders\Demo\Support;

use RuntimeException;

/**
 * One module (lesson) of an interactive demo course, written as a single Markdown file:
 *
 *     ---
 *     title: Falling around: orbits
 *     summary: One line for the syllabus.
 *     duration: 45 min
 *     ---
 *
 *     ::: interactive title="Inertia" start=inertia end=why-no-fall duration="6 min"
 *     Text shown beside or over the simulation (Markdown, {{src:G-03}} for a citation).
 *     :::
 *
 *     ::: richtext title="The explanation" duration="8 min"
 *     Markdown.
 *     :::
 *
 *     ::: layout title="Key ideas" duration="5 min" sources=G-01,G-02
 *     {"document": [ {"component": "FlipCards", "props": {...}} ], "fallback": "Markdown"}
 *     :::
 *
 *     ::: quiz title="Check yourself" duration="8 min" pass=60
 *     ::Question:: Text {=right ~wrong ~wrong}
 *
 *     ::Next:: Text {T}
 *     :::
 *
 * A block starts with `::: <kind> attributes` and ends with a line that is only `:::`. The body of a
 * `quiz` is GIFT (questions separated by a blank line, `//` lines are comments), of a `layout` is
 * JSON, of the others Markdown.
 */
class ModuleFile
{
    public const KINDS = ['interactive', 'richtext', 'layout', 'quiz'];

    /**
     * @return array{meta: array<string, string>, blocks: array<int, array{kind: string, attrs: array<string, string>, body: string}>}
     */
    public static function parse(string $text, string $name = 'module'): array
    {
        $text = str_replace("\r\n", "\n", $text);
        $meta = [];
        if (preg_match('/\A---\n(.*?)\n---\n/s', $text, $m)) {
            foreach (explode("\n", $m[1]) as $line) {
                if (preg_match('/^(\w+):\s*(.*)$/', $line, $kv)) {
                    $meta[$kv[1]] = trim($kv[2]);
                }
            }
            $text = substr($text, strlen($m[0]));
        }

        $blocks = [];
        $current = null;
        foreach (explode("\n", $text) as $number => $line) {
            if ($current === null) {
                if (preg_match('/^:::\s+(\w+)(.*)$/', $line, $m)) {
                    if (!in_array($m[1], self::KINDS, true)) {
                        throw new RuntimeException(sprintf('%s: unknown block "%s" near line %d', $name, $m[1], $number + 1));
                    }
                    $current = ['kind' => $m[1], 'attrs' => self::attributes($m[2]), 'body' => []];
                } elseif (trim($line) !== '') {
                    throw new RuntimeException(sprintf('%s: text outside a block near line %d', $name, $number + 1));
                }
                continue;
            }
            if (rtrim($line) === ':::') {
                $current['body'] = trim(implode("\n", $current['body']));
                $blocks[] = $current;
                $current = null;
                continue;
            }
            $current['body'][] = $line;
        }
        if ($current !== null) {
            throw new RuntimeException("$name: the last block is not closed with :::");
        }

        return ['meta' => $meta, 'blocks' => $blocks];
    }

    /** @return array<string, string> */
    private static function attributes(string $text): array
    {
        $attrs = [];
        preg_match_all('/(\w+)=(?:"([^"]*)"|(\S+))/', $text, $all, PREG_SET_ORDER);
        foreach ($all as $m) {
            $attrs[$m[1]] = isset($m[3]) ? $m[3] : $m[2];
        }

        return $attrs;
    }

    /**
     * GIFT questions of a quiz body, in order.
     *
     * @return array<int, string>
     */
    public static function giftQuestions(string $body): array
    {
        $lines = array_filter(explode("\n", $body), fn (string $l) => !str_starts_with(ltrim($l), '//'));
        $questions = preg_split('/\n\s*\n/', trim(implode("\n", $lines))) ?: [];

        return array_values(array_filter(array_map('trim', $questions), fn (string $q) => $q !== ''));
    }
}
