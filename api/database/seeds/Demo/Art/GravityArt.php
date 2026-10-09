<?php

namespace Database\Seeders\Demo\Art;

use Database\Seeders\Demo\Support\Canvas;

/**
 * Illustrations for "Gravity Lab": near-black space, thin orbit rules, a cyan accent and
 * a gold secondary (the gravity theme preset).
 */
class GravityArt
{
    public const SPACE = '#05070F';
    public const SURFACE = '#0E1424';
    public const TEXT = '#E8ECF5';
    public const CYAN = '#3DD6F5';
    public const GOLD = '#FFB547';
    public const RULE = '#1B2742';

    private static function sky(int $w, int $h): Canvas
    {
        $c = new Canvas($w, $h, self::SPACE);
        $c->speckle((int) ($w * $h / 1800), self::TEXT, 11, 2.0, 40);
        $c->speckle((int) ($w * $h / 14000), self::CYAN, 12, 2.6, 20);

        return $c;
    }

    /** Concentric orbits around a sun with a few planets on them. */
    private static function orbits(Canvas $c, float $cx, float $cy, float $s): void
    {
        $c->circle($cx, $cy, 90 * $s, self::GOLD);
        foreach ([[170, 20, self::CYAN], [260, 95, self::TEXT], [360, 200, self::GOLD], [470, 310, self::CYAN]] as [$r, $angle, $color]) {
            $c->ring($cx, $cy, $r * 2 * $s, $r * 2 * $s, self::RULE, max(1, (int) round(2 * $s)));
            $x = $cx + cos(deg2rad($angle)) * $r * $s;
            $y = $cy + sin(deg2rad($angle)) * $r * $s;
            $c->circle($x, $y, (14 + $r / 40) * $s, $color);
        }
    }

    public static function cover(string $kicker, string $title, string $subtitle, int $w = 1600, int $h = 900): Canvas
    {
        $c = self::sky($w, $h);
        $s = $w / 1600;
        self::orbits($c, 1260 * $s, 470 * $s, 0.8 * $s);
        $c->roundRect(80 * $s, 120 * $s, $c->textWidth($kicker, 18 * $s, 'mono', true) + 48 * $s, 52 * $s, 8 * $s, self::CYAN);
        $c->text($kicker, 104 * $s, 132 * $s, 18 * $s, self::SPACE, 'mono', true);
        $y = $c->paragraph($title, 80 * $s, 220 * $s, 760 * $s, 64 * $s, self::TEXT, 'sans', true, 1.08);
        $c->paragraph($subtitle, 80 * $s, $y + 20 * $s, 700 * $s, 26 * $s, self::CYAN, 'sans', false, 1.35);
        $c->text('Gravity Lab', 80 * $s, $h - 110 * $s, 20 * $s, self::GOLD, 'mono', true);

        return $c;
    }
}
