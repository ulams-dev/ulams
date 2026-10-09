<?php

namespace Database\Seeders\Demo\Art;

use Database\Seeders\Demo\Support\Canvas;

/**
 * Illustrations for "The Scottish Book": a notebook page of squared paper with a ruled
 * margin, blue ink and a marginal red (the ulam theme preset).
 */
class UlamArt
{
    public const PAPER = '#F7F3E8';
    public const SURFACE = '#FFFDF7';
    public const INK = '#1E2230';
    public const BLUE = '#1D3B8F';
    public const RED = '#B23A2E';
    public const GRID = '#E2DCC8';

    private static function page(int $w, int $h): Canvas
    {
        $c = new Canvas($w, $h, self::PAPER);
        $step = max(8, (int) ($w / 64));
        for ($x = 0; $x < $w; $x += $step) {
            $c->line($x, 0, $x, $h, self::GRID, 1);
        }
        for ($y = 0; $y < $h; $y += $step) {
            $c->line(0, $y, $w, $y, self::GRID, 1);
        }

        return $c;
    }

    private static function isPrime(int $n): bool
    {
        if ($n < 2) {
            return false;
        }
        for ($i = 2; $i * $i <= $n; $i++) {
            if ($n % $i === 0) {
                return false;
            }
        }

        return true;
    }

    /** Dots on the square spiral, filled where the number is prime. */
    private static function spiral(Canvas $c, float $cx, float $cy, float $step, int $count): void
    {
        $x = 0;
        $y = 0;
        $dx = 1;
        $dy = 0;
        $segment = 1;
        $passed = 0;
        $turns = 0;
        for ($n = 1; $n <= $count; $n++) {
            if (self::isPrime($n)) {
                $c->circle($cx + $x * $step, $cy + $y * $step, $step * 0.7, self::BLUE);
            }
            $x += $dx;
            $y += $dy;
            if (++$passed === $segment) {
                $passed = 0;
                [$dx, $dy] = [-$dy, $dx];
                if (++$turns % 2 === 0) {
                    $segment++;
                }
            }
        }
    }

    public static function cover(string $kicker, string $title, string $subtitle, int $w = 1600, int $h = 900): Canvas
    {
        $c = self::page($w, $h);
        $s = $w / 1600;
        self::spiral($c, 1200 * $s, 450 * $s, 11 * $s, 1600);
        $c->rect(70 * $s, 0, 3 * $s, $h, self::RED);
        $c->text($kicker, 110 * $s, 130 * $s, 20 * $s, self::RED, 'mono', true);
        $y = $c->paragraph($title, 110 * $s, 190 * $s, 760 * $s, 62 * $s, self::INK, 'serif', true, 1.1);
        $c->paragraph($subtitle, 110 * $s, $y + 20 * $s, 700 * $s, 26 * $s, self::BLUE, 'serif', false, 1.35, true);
        $c->text('Problem 1, with a notebook and a pen', 110 * $s, $h - 110 * $s, 20 * $s, self::INK, 'mono');

        return $c;
    }
}
