<?php

namespace Ulams\CourseBuilder\Apply;

use Ulams\CourseBuilder\Blueprint\Checks;

/**
 * Deterministic Markdown of a lesson for the RichText topic: the blocks in order, callouts as
 * quotes, and a "Sources" list with the cited sections at the end. Raw HTML never reaches the LMS:
 * it is removed here (outside code) in addition to the validator that rejects it.
 */
final class LessonMarkdown
{
    /** @param array<string,string> $labels fragment id → "§2.3 Title" */
    public static function render(array $lesson, array $labels, string $sourceTitle = ''): string
    {
        $parts = [];
        $cited = [];
        foreach ($lesson['blocks'] as $block) {
            $markdown = self::sanitise(trim((string) $block['markdown']));
            if ($block['kind'] === 'callout') {
                $markdown = implode("\n", array_map(fn ($l) => '> ' . $l, explode("\n", $markdown)));
            }
            $parts[] = $markdown;
            foreach ($block['citations'] as $id) {
                $cited[$id] = true;
            }
        }
        if ($cited !== []) {
            $list = [];
            foreach (array_keys($cited) as $i => $id) {
                $list[] = ($i + 1) . '. ' . ($labels[$id] ?? $id) . ($sourceTitle !== '' ? " — {$sourceTitle}" : '');
            }
            $parts[] = "---\n\n**Sources**\n\n" . implode("\n", $list);
        }

        return implode("\n\n", $parts) . "\n";
    }

    /** Strips HTML tags, script links and remote images outside code. */
    public static function sanitise(string $markdown): string
    {
        if (Checks::markup($markdown, 'x') === []) {
            return $markdown;
        }
        $segments = preg_split('/(```.*?```|~~~.*?~~~|`[^`\n]*`)/s', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        foreach ($segments as $i => $segment) {
            if ($i % 2 === 1) {
                continue;
            }
            $segment = (string) preg_replace('/<\s*\/?\s*[a-zA-Z][^<>]*>/', '', $segment);
            $segment = (string) preg_replace('/\]\(\s*(javascript|data|vbscript):[^)]*\)/i', '](#)', $segment);
            $segment = (string) preg_replace('/!\[([^\]]*)\]\(\s*https?:[^)]*\)/i', '$1', $segment);
            $segments[$i] = $segment;
        }

        return implode('', $segments);
    }
}
