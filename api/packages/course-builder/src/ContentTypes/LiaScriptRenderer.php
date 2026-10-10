<?php

namespace Ulams\CourseBuilder\ContentTypes;

use Ulams\CourseBuilder\Apply\LessonMarkdown;

/**
 * Deterministic LiaScript Markdown for a lesson (ADR 0050): the cited blocks in order, the
 * self-checks in LiaScript's quiz syntax, a "Sources" list. The model never writes LiaScript: it
 * fills structured blocks and questions, and this class turns them into text. LiaScript can run
 * code (`@eval`, `@input`, `<script>`) and import macros from other sites; none of it may come from
 * model output, so those constructs are removed here as well as rejected by `unsafe()`.
 */
final class LiaScriptRenderer
{
    /** Macros that execute code or load it from elsewhere. */
    private const UNSAFE = '/(^|[\s(])@(?:eval|input|[A-Za-z0-9_]+\.(?:eval|terminal|exec|run|repl|js))\b|^\s*(?:import|script|link):|<\s*script\b|\bjavascript:/im';

    /** @param array<string,string> $labels fragment id → "§2.3 Title" */
    public static function render(array $lesson, array $labels, string $sourceTitle = '', string $language = 'en'): string
    {
        $parts = ["<!--\nlanguage: " . (preg_match('/^[a-z]{2}$/', $language) ? $language : 'en') . "\n-->", '# ' . self::line($lesson['title'])];
        if (trim((string) ($lesson['summary'] ?? '')) !== '') {
            $parts[] = '> ' . self::line((string) $lesson['summary']);
        }
        $cited = [];
        foreach ($lesson['blocks'] ?? [] as $block) {
            $markdown = self::clean(LessonMarkdown::sanitise(trim((string) $block['markdown'])));
            if ($block['kind'] === 'callout') {
                $markdown = implode("\n", array_map(fn ($l) => '> ' . $l, explode("\n", $markdown)));
            }
            $parts[] = $markdown;
            foreach ($block['citations'] ?? [] as $id) {
                $cited[$id] = true;
            }
        }
        $checks = array_values(array_filter($lesson['selfChecks'] ?? [], fn ($q) => is_array($q)));
        if ($checks !== []) {
            $parts[] = '## Check yourself';
            foreach ($checks as $check) {
                $parts[] = self::question($check);
                foreach ($check['citations'] ?? [] as $id) {
                    $cited[$id] = true;
                }
            }
        }
        if ($cited !== []) {
            $list = [];
            foreach (array_keys($cited) as $i => $id) {
                $list[] = ($i + 1) . '. ' . ($labels[$id] ?? $id) . ($sourceTitle !== '' ? ' — ' . self::line($sourceTitle) : '');
            }
            $parts[] = "---\n\n**Sources**\n\n" . implode("\n", $list);
        }

        return implode("\n\n", $parts) . "\n";
    }

    /** One question in LiaScript quiz syntax. */
    public static function question(array $q): string
    {
        $stem = self::line($q['stem']);
        $options = $q['options'] ?? [];
        $lines = match ($q['type']) {
            'single', 'truefalse' => array_map(fn ($o) => '- [(' . ($o['correct'] ? 'X' : ' ') . ')] ' . self::line($o['text']), $options),
            'multiple' => array_map(fn ($o) => '- [[' . ($o['correct'] ? 'X' : ' ') . ']] ' . self::line($o['text']), $options),
            'short' => ['[[' . implode('|', array_map(fn ($o) => str_replace(['|', '[', ']'], ' ', self::line($o['text'])), $options)) . ']]'],
            default => [],
        };
        $explanation = trim(self::line((string) ($q['explanation'] ?? '')));

        return $stem . "\n\n" . implode("\n", $lines) . ($explanation !== '' ? "\n***\n{$explanation}\n***" : '');
    }

    /** True when the text carries LiaScript code execution or remote imports. */
    public static function unsafe(string $text): bool
    {
        return (bool) preg_match(self::UNSAFE, $text);
    }

    /** Removes the lines and tokens that run code or import from other sites. */
    private static function clean(string $markdown): string
    {
        $kept = [];
        foreach (explode("\n", $markdown) as $line) {
            if (preg_match('/^\s*(?:import|script|link):/i', $line)) {
                continue;
            }
            $kept[] = (string) preg_replace('/(^|[\s(])@(?:eval|input|[A-Za-z0-9_]+\.(?:eval|terminal|exec|run|repl|js))\b/i', '$1', $line);
        }

        return implode("\n", $kept);
    }

    private static function line(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($text)));
    }
}
