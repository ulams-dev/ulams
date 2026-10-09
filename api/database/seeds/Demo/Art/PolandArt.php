<?php

namespace Database\Seeders\Demo\Art;

use Database\Seeders\Demo\Support\Canvas;

/**
 * Illustrations for "Poland, measured": cartographic paper, graticule hairlines, a red
 * accent and a teal secondary (the poland theme preset).
 */
class PolandArt
{
    public const PAPER = '#F4EFE6';
    public const SURFACE = '#FBF8F2';
    public const INK = '#1B2A3A';
    public const RED = '#C8102E';
    public const TEAL = '#5E8C8A';
    public const HAIRLINE = '#D6CDBB';

    private static function map(int $w, int $h): Canvas
    {
        $c = new Canvas($w, $h, self::PAPER);
        for ($x = 0; $x < $w; $x += (int) ($w / 16)) {
            $c->line($x, 0, $x, $h, self::HAIRLINE, 1);
        }
        for ($y = 0; $y < $h; $y += (int) ($h / 9)) {
            $c->line(0, $y, $w, $y, self::HAIRLINE, 1);
        }

        return $c;
    }

    /** Abstract bar chart and contour rings: data on a map, no borders drawn. */
    private static function data(Canvas $c, float $x, float $y, float $s): void
    {
        foreach ([[300, 0.35], [230, 0.55], [160, 0.75]] as [$r, $a]) {
            $c->ring($x, $y, $r * 2 * $s, $r * 1.7 * $s, self::TEAL, max(2, (int) round(3 * $s)));
        }
        foreach ([120, 180, 150, 260, 320, 290, 380] as $i => $height) {
            $c->rect($x - 330 * $s + $i * 62 * $s, $y + 330 * $s - $height * $s, 40 * $s, $height * $s, $i === 6 ? self::RED : self::INK);
        }
    }

    public static function cover(string $kicker, string $title, string $subtitle, int $w = 1600, int $h = 900): Canvas
    {
        $c = self::map($w, $h);
        $s = $w / 1600;
        self::data($c, 1200 * $s, 400 * $s, $s);
        $c->rect(0, 0, 36 * $s, $h, self::RED);
        $c->roundRect(100 * $s, 120 * $s, $c->textWidth($kicker, 18 * $s, 'sans', true) + 48 * $s, 52 * $s, 4 * $s, self::RED);
        $c->text($kicker, 124 * $s, 132 * $s, 18 * $s, '#FFFFFF', 'sans', true);
        $y = $c->paragraph($title, 100 * $s, 220 * $s, 720 * $s, 66 * $s, self::INK, 'serif', true, 1.08);
        $c->paragraph($subtitle, 100 * $s, $y + 20 * $s, 700 * $s, 26 * $s, self::TEAL, 'sans', false, 1.35);
        $c->text('Cited public data, English and Polish', 100 * $s, $h - 110 * $s, 20 * $s, self::INK, 'sans', true);

        return $c;
    }
}
