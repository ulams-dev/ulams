<?php

namespace Ulams\CourseBuilder\Publish;

/**
 * Deterministic accessibility checks on generated Markdown: heading order, link text, image
 * alternative text and table header rows. Code blocks are ignored.
 */
final class MarkdownAccessibility
{
    /** @return string[] problems, each a short sentence */
    public static function check(string $markdown): array
    {
        $text = (string) preg_replace('/```.*?```|~~~.*?~~~/s', '', $markdown);
        $text = (string) preg_replace('/`[^`\n]*`/', '', $text);
        $problems = [];

        $previous = 1; // the lesson title is the page's level-1 heading
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (preg_match('/^(#{1,6})\s+\S/', $line, $m)) {
                $level = strlen($m[1]);
                if ($level > $previous + 1) {
                    $problems[] = "heading level jumps from h{$previous} to h{$level}";
                }
                $previous = $level;
            }
        }
        if (preg_match('/(?<!!)\[\s*\]\([^)]*\)/', $text)) {
            $problems[] = 'a link has no text';
        }
        if (preg_match('/!\[\s*\]\([^)]*\)/', $text)) {
            $problems[] = 'an image has no alternative text';
        }
        $lines = preg_split('/\R/', $text) ?: [];
        foreach ($lines as $i => $line) {
            $isRow = preg_match('/^\s*\|.*\|\s*$/', $line) === 1;
            $prevIsRow = $i > 0 && preg_match('/^\s*\|.*\|\s*$/', $lines[$i - 1]) === 1;
            if ($isRow && !$prevIsRow) {
                $next = $lines[$i + 1] ?? '';
                if (!preg_match('/^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/', $next)) {
                    $problems[] = 'a table has no header row';
                    break;
                }
            }
        }

        return array_values(array_unique($problems));
    }
}
