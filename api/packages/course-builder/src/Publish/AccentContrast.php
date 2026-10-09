<?php

namespace Ulams\CourseBuilder\Publish;

/**
 * The accent adjustment of `front/ui/src/theme/contrast.ts`, in PHP: an accent that does not reach 5:1
 * on the theme background is moved towards black or white until it does (WCAG AA for text on cards).
 */
final class AccentContrast
{
    public const BACKGROUNDS = ['coffee' => '#f6f1e9', 'oncall' => '#0b0f14', 'nightsky' => '#13153a', 'gravity' => '#05070f', 'poland' => '#f4efe6', 'ulam' => '#f7f3e8'];

    /** @return array{value:string,adjusted:bool}|null */
    public static function adjust(string $accent, string $preset, float $ratio = 5.0): ?array
    {
        $rgb = self::parse($accent);
        $bg = self::parse(self::BACKGROUNDS[$preset] ?? '');
        if ($rgb === null || $bg === null) {
            return null;
        }
        $out = self::readableOn($rgb, $bg, $ratio);
        $value = self::hex($out);

        return ['value' => $value, 'adjusted' => $value !== self::hex($rgb)];
    }

    /** @return int[]|null */
    private static function parse(string $hex): ?array
    {
        return preg_match('/^#([0-9a-f]{6})$/i', trim($hex), $m) ? array_map('hexdec', str_split($m[1], 2)) : null;
    }

    private static function hex(array $rgb): string
    {
        return '#' . implode('', array_map(fn ($c) => str_pad(dechex((int) max(0, min(255, round($c)))), 2, '0', STR_PAD_LEFT), $rgb));
    }

    private static function luminance(array $rgb): float
    {
        [$r, $g, $b] = array_map(function ($c) {
            $s = $c / 255;

            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private static function contrast(array $a, array $b): float
    {
        $l = [self::luminance($a), self::luminance($b)];
        rsort($l);

        return ($l[0] + 0.05) / ($l[1] + 0.05);
    }

    private static function readableOn(array $accent, array $bg, float $ratio): array
    {
        if (self::contrast($accent, $bg) >= $ratio) {
            return $accent;
        }
        $target = self::luminance($bg) > 0.5 ? [0, 0, 0] : [255, 255, 255];
        for ($t = 0.04; $t <= 1.0001; $t += 0.04) {
            $candidate = array_map(fn ($i) => $accent[$i] + ($target[$i] - $accent[$i]) * $t, [0, 1, 2]);
            if (self::contrast($candidate, $bg) >= $ratio) {
                return $candidate;
            }
        }

        return $target;
    }
}
