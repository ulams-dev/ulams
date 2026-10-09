<?php

namespace Ulams\CourseBuilder\Apply;

use InvalidArgumentException;

/**
 * Renders a structured blueprint question as GIFT, deterministically. The model never writes
 * GIFT: special characters are escaped here, so a question cannot break out of its braces.
 *
 * - single → `{=right#why ~wrong ~wrong}` (multiple_choice)
 * - multiple → `{~%50%right ~%50%right ~%-100%wrong}` (multiple answers)
 * - truefalse → `{T}` / `{F}`
 * - short → `{=answer =other spelling}` (short answers)
 */
final class GiftRenderer
{
    public static function render(array $question): string
    {
        $stem = self::escape(self::plain($question['stem']));
        $explanation = trim(self::plain((string) ($question['explanation'] ?? '')));
        $options = $question['options'] ?? [];

        $body = match ($question['type']) {
            'single' => implode(' ', array_map(
                fn ($o) => ($o['correct'] ? '=' : '~') . self::escape(self::plain($o['text'])) . ($o['correct'] && $explanation !== '' ? '#' . self::escape($explanation) : ''),
                $options,
            )),
            'multiple' => self::weighted($options),
            'truefalse' => self::trueFalse($options),
            'short' => implode(' ', array_map(fn ($o) => '=' . self::escape(self::plain($o['text'])), $options)),
            default => throw new InvalidArgumentException('Unknown question type ' . $question['type']),
        };

        return "{$stem} {{$body}}";
    }

    private static function weighted(array $options): string
    {
        $right = array_values(array_filter($options, fn ($o) => $o['correct']));
        $wrong = array_values(array_filter($options, fn ($o) => !$o['correct']));
        $plus = self::percent(100 / max(1, count($right)));
        $minus = self::percent(100 / max(1, count($wrong)));

        return implode(' ', array_map(
            fn ($o) => '~%' . ($o['correct'] ? $plus : '-' . $minus) . '%' . self::escape(self::plain($o['text'])),
            $options,
        ));
    }

    private static function trueFalse(array $options): string
    {
        foreach ($options as $o) {
            if ($o['correct']) {
                return strtolower(trim($o['text'])) === 'true' ? 'T' : 'F';
            }
        }
        throw new InvalidArgumentException('A true/false question needs a correct option.');
    }

    private static function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 5, '.', ''), '0'), '.');
    }

    /** Escapes GIFT control characters: ~ = # { } : and the backslash itself. */
    public static function escape(string $text): string
    {
        return (string) preg_replace('/([\\\\~=#{}:])/', '\\\\$1', $text);
    }

    private static function plain(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($text)));
    }
}
