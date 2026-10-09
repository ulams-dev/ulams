<?php

namespace Ulams\LivingCourse\Diff;

use Normalizer;

/**
 * `norm(text)` of ADR 0031: what must not count as a change. Unicode NFC, line endings, runs of
 * whitespace (outside code fences), emphasis markers (not inside code), typographic quotes and
 * dashes. Code is compared as written.
 */
final class Normaliser
{
    private const MAP = [
        "\u{2018}" => "'", "\u{2019}" => "'", "\u{201A}" => "'", "\u{201B}" => "'",
        "\u{201C}" => '"', "\u{201D}" => '"', "\u{201E}" => '"', "\u{201F}" => '"',
        "\u{2013}" => '-', "\u{2014}" => '-', "\u{2212}" => '-', "\u{2026}" => '...',
        "\u{00A0}" => ' ', "\u{2009}" => ' ', "\u{200B}" => '',
    ];

    public static function text(string $text): string
    {
        if (class_exists(Normalizer::class)) {
            $text = Normalizer::normalize($text, Normalizer::FORM_C) ?: $text;
        }
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $parts = preg_split('/(^[ \t]{0,3}(?:```|~~~).*?^[ \t]{0,3}(?:```|~~~)[ \t]*$)/ms', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $out = '';
        foreach ($parts as $i => $part) {
            $out .= $i % 2 === 1 ? "\n" . trim($part, "\n") . "\n" : self::prose($part);
        }

        return trim((string) preg_replace('/[ \t]+\n|\n[ \t]+/', "\n", trim($out)));
    }

    public static function hash(string $text): string
    {
        return hash('sha256', self::text($text));
    }

    private static function prose(string $text): string
    {
        $pieces = preg_split('/(`[^`\n]*`)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $out = '';
        foreach ($pieces as $i => $piece) {
            if ($i % 2 === 1) {
                $out .= $piece;
                continue;
            }
            $piece = strtr($piece, self::MAP);
            // emphasis markers: asterisks, and underscores that do not sit inside a word (snake_case stays)
            $piece = (string) preg_replace('/\*+/', '', $piece);
            $piece = (string) preg_replace('/(?<![\p{L}\p{N}])_+|_+(?![\p{L}\p{N}])/u', '', $piece);
            $out .= (string) preg_replace('/\s+/u', ' ', $piece);
        }

        return $out;
    }
}
