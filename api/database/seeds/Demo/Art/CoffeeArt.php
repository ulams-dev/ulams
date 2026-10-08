<?php

namespace Database\Seeders\Demo\Art;

use Database\Seeders\Demo\Support\Canvas;

/**
 * Illustrations and printables for "The Coffee Atlas": warm editorial
 * palette, serif headlines, hairline rules.
 */
class CoffeeArt
{
    public const PAPER = '#F6F1E9';
    public const INK = '#2B1D14';
    public const ACCENT = '#C2552D';
    public const SAGE = '#7A8B6F';
    public const RULE = '#D9CFC2';
    public const CHERRY = '#B3261E';

    /** Rough continent outlines (lon, lat) for an illustrated, not cartographic, map. */
    private const LAND = [
        [[-168, 65], [-140, 70], [-95, 72], [-80, 63], [-62, 56], [-55, 48], [-70, 43], [-76, 35], [-81, 26], [-90, 29], [-97, 26], [-97, 18], [-88, 16], [-83, 9], [-78, 8], [-86, 12], [-92, 15], [-105, 20], [-112, 29], [-118, 34], [-124, 42], [-124, 49], [-135, 58], [-152, 59], [-165, 60]],
        [[-80, 9], [-72, 12], [-62, 11], [-51, 4], [-35, -6], [-39, -15], [-41, -22], [-48, -27], [-53, -34], [-58, -38], [-65, -42], [-68, -54], [-74, -50], [-73, -38], [-71, -18], [-77, -12], [-81, -5]],
        [[-10, 36], [-9, 44], [-2, 47], [-4, 49], [5, 54], [8, 58], [5, 62], [12, 68], [28, 71], [40, 66], [42, 55], [40, 45], [29, 41], [22, 37], [18, 40], [12, 44], [8, 44], [3, 42], [-5, 36]],
        [[-17, 21], [-13, 28], [-10, 35], [0, 36], [10, 37], [20, 32], [32, 31], [35, 25], [38, 18], [43, 12], [51, 12], [47, 5], [40, -3], [40, -11], [35, -20], [33, -26], [28, -33], [20, -35], [17, -29], [12, -17], [13, -6], [9, 4], [3, 6], [-8, 4], [-13, 8], [-17, 14]],
        [[40, 45], [42, 55], [40, 66], [60, 70], [80, 73], [100, 77], [130, 72], [160, 70], [180, 67], [175, 62], [160, 58], [155, 52], [140, 46], [130, 42], [122, 37], [122, 30], [117, 23], [108, 21], [106, 17], [109, 12], [104, 9], [100, 13], [100, 2], [103, 1], [98, 8], [97, 16], [92, 21], [88, 22], [80, 15], [77, 8], [73, 17], [70, 22], [62, 25], [57, 25], [52, 28], [48, 30], [44, 29], [36, 34], [35, 37], [30, 37], [27, 40], [33, 42]],
        [[35, 30], [39, 21], [43, 13], [49, 14], [53, 16], [59, 22], [56, 26], [51, 24], [48, 29], [38, 30]],
        [[95, 5], [104, -2], [106, -6], [102, -5], [96, 2]],
        [[109, 1], [113, 3], [117, 7], [119, 1], [116, -4], [110, -3]],
        [[105, -6], [114, -7], [114, -8.5], [106, -7.5]],
        [[119, 1], [125, 1], [121, -1], [123, -5], [120, -5]],
        [[131, -1], [141, -3], [150, -10], [141, -9], [136, -5]],
        [[114, -22], [122, -17], [130, -12], [137, -12], [142, -11], [146, -19], [153, -26], [150, -37], [141, -38], [136, -35], [131, -31], [115, -34]],
        [[44, -25], [47, -15], [50, -13], [49, -21], [46, -25]],
        [[-55, 60], [-42, 60], [-20, 70], [-25, 81], [-60, 82], [-72, 77]],
        [[-6, 50], [2, 51], [-2, 56], [-5, 58], [-6, 55]],
        [[130, 31], [135, 34], [140, 35], [142, 40], [142, 44], [139, 40], [133, 34]],
        [[120, 18], [122, 18], [126, 7], [122, 7]],
        [[80, 10], [82, 7], [80, 6]],
        [[-85, 22], [-74, 20], [-80, 22]],
    ];

    /** name, region, lat, lon, species, altitude */
    public const ORIGINS = [
        ['Huila', 'Colombia', 2.5, -75.6, 'Arabica', '1,500–2,000 m', 'r', 0],
        ['Antigua', 'Guatemala', 14.6, -90.7, 'Arabica', '1,500–1,700 m', 'l', -14],
        ['Tarrazú', 'Costa Rica', 9.6, -84.0, 'Arabica', '1,200–1,900 m', 'l', 10],
        ['Cajamarca', 'Peru', -6.5, -78.5, 'Arabica', '1,400–2,000 m', 'l', 6],
        ['Sul de Minas', 'Brazil', -21.5, -45.4, 'Arabica', '800–1,300 m', 'r', 0],
        ['Kona', 'Hawaii', 19.6, -155.9, 'Arabica', '150–900 m', 'r', 0],
        ['Yirgacheffe', 'Ethiopia', 6.2, 38.2, 'Arabica', '1,700–2,200 m', 'r', -6],
        ['Nyeri', 'Kenya', -0.4, 36.9, 'Arabica', '1,500–2,100 m', 'r', 22],
        ['Huye', 'Rwanda', -2.6, 29.7, 'Arabica', '1,600–2,000 m', 'l', 34],
        ['Haraaz', 'Yemen', 15.3, 43.9, 'Arabica', '2,000–2,400 m', 'r', -8],
        ['Mukono', 'Uganda', 0.4, 32.8, 'Robusta', '900–1,300 m', 'l', -14],
        ['Chikmagalur', 'India', 13.3, 75.8, 'Both', '600–1,500 m', 'l', 18],
        ['Đắk Lắk', 'Vietnam', 12.7, 108.0, 'Robusta', '400–800 m', 'r', -10],
        ['Aceh', 'Sumatra', 4.6, 96.8, 'Arabica', '1,100–1,600 m', 'l', 4],
        ['Goroka', 'Papua New Guinea', -6.1, 145.4, 'Arabica', '1,300–1,900 m', 'r', 20],
    ];

    public static function coverImage(string $kicker, string $title, string $subtitle, int $w = 1600, int $h = 900): Canvas
    {
        $c = new Canvas($w, $h, self::PAPER);
        $c->speckle(2600, self::INK, 11, 1.4, 112);
        $s = $w / 1600;
        $c->line(80 * $s, 80 * $s, $w - 80 * $s, 80 * $s, self::INK, 2);
        $c->text('THE COFFEE ATLAS', 80 * $s, 96 * $s, 16 * $s, self::INK, 'sans', true);
        $c->text('No. 1 · Seed to cup', $w - 330 * $s, 96 * $s, 16 * $s, self::SAGE, 'serif', false, true);
        self::cherryBranch($c, $w * 0.80, $h * 0.70, 1.05 * $s);
        $c->text($kicker, 80 * $s, 250 * $s, 22 * $s, self::ACCENT, 'serif', false, true);
        $y = $c->paragraph($title, 80 * $s, 290 * $s, 820 * $s, 56 * $s, self::INK, 'serif', true, 1.05);
        $c->line(80 * $s, $y + 10 * $s, 360 * $s, $y + 10 * $s, self::ACCENT, 3);
        $c->paragraph($subtitle, 80 * $s, $y + 40 * $s, 760 * $s, 26 * $s, self::INK, 'serif', false, 1.35, true);
        $c->line(80 * $s, $h - 80 * $s, $w - 80 * $s, $h - 80 * $s, self::RULE, 1);
        $c->text('Inés Duarte · Tomasz Wierzba', 80 * $s, $h - 66 * $s, 16 * $s, self::SAGE, 'sans');

        return $c;
    }

    /** Hand-drawn style coffee branch with leaves and cherries. */
    public static function cherryBranch(Canvas $c, float $x, float $y, float $scale = 1.0): void
    {
        $branch = [];
        for ($i = 0; $i <= 20; $i++) {
            $t = $i / 20;
            $branch[] = [$x - 360 * $scale + 720 * $scale * $t, $y + sin($t * 3.0) * 40 * $scale - 120 * $scale * $t];
        }
        $c->polyline($branch, self::INK, (int) max(2, 5 * $scale));
        foreach ([2, 5, 8, 11, 14, 17] as $k => $i) {
            [$bx, $by] = $branch[$i];
            $dir = $k % 2 ? 1 : -1;
            $leaf = [];
            for ($a = 0; $a <= 12; $a++) {
                $t = $a / 12;
                $leaf[] = [$bx + 150 * $scale * $t, $by + $dir * (110 * $scale * $t) + $dir * sin($t * M_PI) * 40 * $scale];
            }
            for ($a = 12; $a >= 0; $a--) {
                $t = $a / 12;
                $leaf[] = [$bx + 150 * $scale * $t, $by + $dir * (110 * $scale * $t) - $dir * sin($t * M_PI) * 20 * $scale];
            }
            $c->polygon($leaf, self::SAGE, 10);
            $c->line($bx, $by, $bx + 150 * $scale, $by + $dir * 110 * $scale, self::INK, 1);
        }
        foreach ([4, 7, 10, 13, 16] as $k => $i) {
            [$bx, $by] = $branch[$i];
            foreach ([[-14, 18], [12, 22], [0, 40], [-22, 44], [20, 50]] as $j => [$dx, $dy]) {
                if ($j > 2 + $k % 3) {
                    continue;
                }
                $c->circle($bx + $dx * $scale, $by + $dy * $scale, 30 * $scale, $j % 2 ? '#8E1C16' : self::CHERRY);
                $c->circle($bx + ($dx - 5) * $scale, $by + ($dy - 6) * $scale, 8 * $scale, '#FFFFFF', 70);
            }
        }
    }

    public static function beltMap(): Canvas
    {
        $w = 1600;
        $h = 900;
        $c = new Canvas($w, $h, self::PAPER);
        $c->speckle(1800, self::INK, 3, 1.2, 115);
        // equirectangular, 4 px per degree, latitudes 62 N to 46 S
        $map = fn (float $lat, float $lon) => [80 + ($lon + 180) * 4, 130 + (62 - $lat) * 4];
        [, $north] = $map(25, 0);
        [, $south] = $map(-30, 0);
        $c->rect(80, $north, 1440, $south - $north, self::ACCENT, 108);
        foreach ([[23.4, 'Tropic of Cancer'], [0, 'Equator'], [-23.4, 'Tropic of Capricorn']] as [$lat, $label]) {
            [, $y] = $map($lat, 0);
            $c->dashed(80, $y, 1520, $y, self::RULE, 6);
            $c->text($label, 84, $y - 18, 12, self::SAGE, 'serif', false, true);
        }
        foreach (self::LAND as $shape) {
            $points = array_map(fn ($p) => $map(max(-46, min(62, $p[1])), $p[0]), $shape);
            $c->polygon($points, '#E6DCCB');
            $points[] = $points[0];
            $c->polyline($points, self::INK, 1, 70);
        }
        $colorFor = fn (string $species) => $species === 'Robusta' ? '#6B4226' : ($species === 'Both' ? self::SAGE : self::ACCENT);
        foreach (self::ORIGINS as $i => [$name, $region, $lat, $lon, $species]) {
            [$x, $y] = $map($lat, $lon);
            $size = $name === 'Huila' ? 34 : 24;
            $c->circle($x, $y, $size + 4, self::PAPER);
            $c->circle($x, $y, $size, $colorFor($species));
            $c->centeredText((string) ($i + 1), $x, $y - 8, 10, '#FFFFFF', 'sans', true);
        }
        $c->text('The coffee belt', 80, 30, 40, self::INK, 'serif', true);
        $c->text('Where coffee grows: between roughly 25° N and 30° S, from sea level to 2,400 m', 80, 86, 18, self::SAGE, 'serif', false, true);
        $top = 586;
        $c->line(80, $top, 1520, $top, self::INK, 2);
        foreach (self::ORIGINS as $i => [$name, $region, , , $species, $alt]) {
            $col = intdiv($i, 5);
            $x = 80 + $col * 330;
            $y = $top + 22 + ($i % 5) * 50;
            $c->circle($x + 12, $y + 12, 24, $colorFor($species));
            $c->centeredText((string) ($i + 1), $x + 12, $y + 4, 10, '#FFFFFF', 'sans', true);
            $c->text($name . ', ' . $region, $x + 34, $y - 2, 14, $name === 'Huila' ? self::ACCENT : self::INK, 'serif', true);
            $c->text($species . ' · ' . $alt, $x + 34, $y + 20, 12, self::SAGE, 'sans');
        }
        $lx = 1080;
        $c->text('Species and altitude', $lx, $top + 18, 15, self::INK, 'serif', true);
        foreach ([[self::ACCENT, 'Arabica · 1,000–2,400 m', 'cool nights, slow ripening, more acidity and aroma'], ['#6B4226', 'Robusta · 0–900 m', 'heat tolerant, twice the caffeine, heavier body'], [self::SAGE, 'Both grown', 'Indian estates shade-grow both species'], [self::ACCENT, 'Huila (1), our cherry', 'grows at 1,750 m above sea level']] as $k => [$color, $line1, $line2]) {
            $y = $top + 52 + $k * 54;
            $c->circle($lx + 10, $y + 10, 18, $color);
            $c->text($line1, $lx + 30, $y, 14, self::INK, 'sans', true);
            $c->text($line2, $lx + 30, $y + 20, 12, self::SAGE, 'sans');
        }

        return $c;
    }

    /** Bean temperature model used for the chart: charge 200 °C, drop 210 °C at 10:45. */
    private static function beanTemp(float $min): float
    {
        if ($min <= 1.25) {
            return 90 + 110 * exp(-$min * 3.2);
        }

        return 252 - (252 - 90) * exp(-0.142 * ($min - 1.25));
    }

    public static function roastCurve(): Canvas
    {
        $w = 1600;
        $h = 900;
        $c = new Canvas($w, $h, self::PAPER);
        $x0 = 140;
        $y0 = 760;
        $pw = 1300;
        $ph = 560;
        $X = fn (float $m) => $x0 + $m / 12 * $pw;
        $Y = fn (float $t) => $y0 - ($t - 50) / 200 * $ph;
        $c->text('The roast curve', 80, 40, 40, self::INK, 'serif', true);
        $c->text('Washed Caturra, Huila · 1 kg batch · charge 200 °C · drop 210 °C at 10:45', 80, 96, 18, self::SAGE, 'serif', false, true);
        $phases = [[0, 4.6, self::SAGE, 'DRYING'], [4.6, 8.5, '#D9B26A', 'MAILLARD'], [8.5, 10.75, self::ACCENT, 'DEVELOPMENT']];
        foreach ($phases as [$a, $b, $col, $label]) {
            $c->rect($X($a), $Y(250), $X($b) - $X($a), $Y(50) - $Y(250), $col, 105);
            $c->centeredText($label, ($X($a) + $X($b)) / 2, $Y(250) + 10, 13, $col === '#D9B26A' ? '#8A6A2A' : $col, 'sans', true);
        }
        for ($m = 0; $m <= 12; $m++) {
            $c->line($X($m), $Y(50), $X($m), $Y(250), self::RULE, 1);
            $c->centeredText($m . ':00', $X($m), $y0 + 12, 13, self::SAGE, 'mono');
        }
        for ($t = 50; $t <= 250; $t += 25) {
            $c->line($x0, $Y($t), $x0 + $pw, $Y($t), self::RULE, 1);
            $c->text($t . ' °C', $x0 - 70, $Y($t) - 8, 13, self::SAGE, 'mono');
        }
        $et = [];
        $bt = [];
        $ror = [];
        for ($i = 0; $i <= 215; $i++) {
            $m = $i * 0.05;
            $bt[] = [$X($m), $Y(self::beanTemp($m))];
            $et[] = [$X($m), $Y(min(248, 215 + 25 * (1 - exp(-$m / 2)) - ($m > 8.5 ? ($m - 8.5) * 6 : 0)))];
            if ($m > 2.2) {
                $rate = (self::beanTemp($m) - self::beanTemp($m - 0.5)) * 2;
                $ror[] = [$X($m), $Y(50 + $rate * 5)];
            }
        }
        $c->polyline($et, self::SAGE, 3);
        $c->polyline($ror, '#8A6A2A', 2);
        $c->polyline($bt, self::INK, 5);
        $c->text('Environment temp.', $X(2.3), $Y(226), 14, self::SAGE, 'sans', true);
        $c->text('Rate of rise (°C/min, ×5 scale)', $X(2.4), $Y(50 + 23 * 5) - 30, 14, '#8A6A2A', 'sans', true);
        $c->text('Bean temp.', $X(3.0), $Y(self::beanTemp(3.0)) + 14, 14, self::INK, 'sans', true);
        $notes = [
            [0, 200, 'Charge 200 °C', 30, -40],
            [1.25, 90, 'Turning point 90 °C · 1:15', 30, 40],
            [4.6, self::beanTemp(4.6), 'Dry end 150 °C · 4:35', 30, 50],
            [8.5, self::beanTemp(8.5), 'First crack 196 °C · 8:30', -330, -46],
            [10.75, self::beanTemp(10.75), 'Drop 210 °C · 10:45', 30, -60],
        ];
        foreach ($notes as [$m, $t, $label, $dx, $dy]) {
            $c->circle($X($m), $Y($t), 16, self::ACCENT);
            $tx = $dx < 0 ? $X($m) + $dx : $X($m) + $dx + 4;
            $c->line($X($m), $Y($t), $dx < 0 ? $X($m) - 20 : $X($m) + $dx, $Y($t) + $dy, self::ACCENT, 2);
            $c->text($label, $tx, $Y($t) + $dy - 9, 15, self::INK, 'serif', true);
        }
        $c->line($X(8.5), $Y(165), $X(10.75), $Y(165), self::ACCENT, 3);
        $c->line($X(8.5), $Y(160), $X(8.5), $Y(170), self::ACCENT, 3);
        $c->line($X(10.75), $Y(160), $X(10.75), $Y(170), self::ACCENT, 3);
        $c->centeredText('Development 2:15', ($X(8.5) + $X(10.75)) / 2, $Y(165) + 10, 15, self::ACCENT, 'serif', true, true);
        $c->centeredText('= 21 % of the roast', ($X(8.5) + $X(10.75)) / 2, $Y(165) + 32, 15, self::ACCENT, 'serif', true, true);
        $c->text('Time (min)', $x0 + $pw - 80, $y0 + 34, 13, self::SAGE, 'sans');

        return $c;
    }

    /**
     * Cross-section of a ripe coffee cherry. Returns the canvas; hotspot
     * positions (percent of width/height) are in cherryHotspots().
     */
    public static function cherryAnatomy(): Canvas
    {
        $c = new Canvas(1200, 800, self::PAPER);
        $c->speckle(900, self::INK, 5, 1.2, 115);
        $cx = 600;
        $cy = 420;
        $ring = fn (float $w, string $color) => $c->ellipse($cx, $cy, $w, $w * 0.889, $color);
        $ring(720, '#8E1C16');
        $ring(704, self::CHERRY);
        $ring(680, '#E8826B');
        $ring(540, '#EBC07A');
        $ring(500, '#F1E7CF');
        $ring(470, '#D8DCD6');
        foreach ([-1, 1] as $side) {
            $bx = $cx + $side * 110;
            $c->ellipse($bx, $cy, 205, 330, '#B9C08F');
            $c->ellipse($bx - $side * 18, $cy, 160, 300, '#C9CF9F');
            $c->line($bx - $side * 96, $cy - 130, $bx - $side * 96, $cy + 130, '#7C8459', 4);
        }
        $c->line($cx, $cy - 170, $cx, $cy + 170, '#9AA08A', 2);
        $c->circle($cx, $cy - 330, 28, '#5B3A1E');
        $c->text('Anatomy of a coffee cherry', 40, 30, 30, self::INK, 'serif', true);
        $c->text('Tap each layer to explore it', 40, 76, 16, self::SAGE, 'serif', false, true);

        return $c;
    }

    /** @return array<int, array{0: float, 1: float, 2: string, 3: string}> x%, y%, title, html */
    public static function cherryHotspots(): array
    {
        return [
            [72.5, 27.4, 'Skin (exocarp)', '<p>A thin, waxy skin that turns from green to deep red (or yellow in some varieties) as the cherry ripens. Pickers in Huila judge ripeness by its colour and by how easily the cherry comes off the branch.</p>'],
            [75.2, 46.6, 'Pulp (mesocarp)', '<p>Sweet, juicy fruit flesh, rich in sugars. In the <strong>washed</strong> process it is removed on the day of picking; in <strong>natural</strong> coffees it dries around the seed for weeks, which is why naturals taste fruitier.</p>'],
            [31.2, 38.1, 'Mucilage', '<p>A sticky, sugary layer clinging to the parchment. Washed coffees ferment it away in tanks for 12–36 hours. <strong>Honey</strong> process coffees are dried with some or all of it left on.</p>'],
            [32.5, 66.0, 'Parchment (endocarp)', '<p>A papery protective shell around each seed. Green coffee is often stored and traded "in parchment" and hulled just before export.</p>'],
            [50.0, 78.5, 'Silver skin', '<p>A very thin seed coat that clings to the bean. Most of it comes off as chaff during roasting.</p>'],
            [59.0, 52.5, 'Bean (seed)', '<p>Usually two seeds face each other with their flat sides. About 5 % of cherries carry a single round seed: the <strong>peaberry</strong>, often sold separately.</p>'],
        ];
    }

    public static function processing(string $method): Canvas
    {
        $c = new Canvas(1200, 700, self::PAPER);
        $c->speckle(900, self::INK, crc32($method) % 100, 1.2, 115);
        $c->rect(60, 60, 1080, 520, '#EFE6D8');
        $c->frame(60, 60, 1080, 520, self::RULE, 2);
        switch ($method) {
            case 'washed':
                $c->rect(200, 260, 800, 260, '#9DB4C0');
                for ($i = 0; $i < 8; $i++) {
                    $c->dashed(220, 290 + $i * 28, 980, 290 + $i * 28, '#C8D8E0', 14, 2);
                }
                for ($i = 0; $i < 60; $i++) {
                    $c->ellipse(240 + ($i * 137) % 720, 420 + ($i * 53) % 80, 34, 24, '#E8DFC4');
                }
                $c->frame(200, 260, 800, 260, self::INK, 3);
                $c->text('Fermentation tank · 18–36 h', 220, 200, 22, self::INK, 'serif', true, true);
                break;
            case 'natural':
                $c->rect(140, 330, 920, 30, '#6B4F3A');
                for ($i = 0; $i < 9; $i++) {
                    $c->line(160 + $i * 110, 360, 160 + $i * 110, 470, '#6B4F3A', 6);
                }
                for ($i = 0; $i < 120; $i++) {
                    $color = ['#8E1C16', '#6E1A12', '#4A1B10'][$i % 3];
                    $c->circle(170 + ($i * 71) % 880, 300 + ($i * 13) % 34, 30, $color);
                }
                $c->circle(1000, 150, 90, '#E9B44C');
                $c->text('Raised drying beds · 3–5 weeks in the sun', 160, 190, 22, self::INK, 'serif', true, true);
                break;
            case 'honey':
                for ($i = 0; $i < 40; $i++) {
                    $x = 200 + ($i * 97) % 800;
                    $y = 260 + ($i * 41) % 230;
                    $c->ellipse($x, $y, 74, 52, '#D99A2B', 30);
                    $c->ellipse($x, $y, 56, 40, '#E8DFC4');
                    $c->line($x, $y - 15, $x, $y + 15, '#B9A77E', 2);
                }
                $c->text('Pulped, dried with sticky mucilage left on', 200, 190, 22, self::INK, 'serif', true, true);
                break;
            default:
                $c->roundRect(420, 180, 360, 360, 40, '#7D8A8F');
                $c->roundRect(440, 200, 320, 320, 30, '#97A4A9');
                $c->rect(585, 120, 30, 70, '#5C6B70');
                $c->circle(600, 115, 50, '#5C6B70');
                for ($i = 0; $i < 14; $i++) {
                    $c->circle(470 + ($i * 37) % 260, 240 + ($i * 61) % 250, 10 + $i % 3 * 6, '#FFFFFF', 70);
                }
                $c->text('Sealed tank, no oxygen · 48–120 h', 160, 300, 22, self::INK, 'serif', true, true);
                $c->text('CO₂ escapes through a one-way valve', 160, 340, 18, self::SAGE, 'serif', false, true);
        }
        $labels = ['washed' => 'Washed', 'natural' => 'Natural', 'honey' => 'Honey', 'anaerobic' => 'Anaerobic'];
        $c->text($labels[$method] ?? ucfirst($method), 60, 600, 40, self::INK, 'serif', true);

        return $c;
    }

    /** Background for the grind-size drag-and-drop: four brew methods. */
    public static function grindBoard(): Canvas
    {
        $c = new Canvas(800, 500, self::PAPER);
        $c->text('Match the grind to the brewer', 24, 16, 22, self::INK, 'serif', true);
        $methods = ['V60', 'French press', 'Espresso', 'Cold brew'];
        foreach ($methods as $i => $name) {
            $x = 20 + $i * 195;
            $c->frame($x, 60, 180, 300, self::RULE, 1);
            $cx = $x + 90;
            switch ($i) {
                case 0:
                    $c->polygon([[$cx - 55, 100], [$cx + 55, 100], [$cx + 12, 190], [$cx - 12, 190]], '#E8E2D6');
                    $c->polyline([[$cx - 55, 100], [$cx - 12, 190], [$cx + 12, 190], [$cx + 55, 100]], self::INK, 3);
                    $c->rect($cx - 40, 192, 80, 8, self::INK);
                    $c->roundRect($cx - 35, 205, 70, 40, 8, '#C8BBA6');
                    break;
                case 1:
                    $c->rect($cx - 35, 105, 70, 140, '#E6EEF0');
                    $c->frame($cx - 35, 105, 70, 140, self::INK, 3);
                    $c->rect($cx - 35, 175, 70, 70, '#6B4226');
                    $c->line($cx, 85, $cx, 170, self::INK, 3);
                    $c->rect($cx - 40, 100, 80, 8, self::INK);
                    break;
                case 2:
                    $c->roundRect($cx - 40, 160, 80, 70, 12, '#FFFFFF');
                    $c->frame($cx - 40, 160, 80, 70, self::INK, 2);
                    $c->ellipse($cx, 165, 70, 14, '#B9834A');
                    $c->rect($cx - 50, 228, 100, 8, self::INK);
                    $c->rect($cx - 60, 110, 120, 20, self::INK);
                    $c->rect($cx - 6, 130, 12, 25, self::INK);
                    break;
                default:
                    $c->roundRect($cx - 40, 100, 80, 150, 14, '#E6EEF0');
                    $c->rect($cx - 40, 150, 80, 100, '#4A2C17');
                    $c->rect($cx - 44, 92, 88, 14, self::SAGE);
                    for ($k = 0; $k < 3; $k++) {
                        $c->rect($cx - 25 + $k * 20, 170 + $k * 20, 14, 14, '#DDE8EC');
                    }
            }
            $c->centeredText($name, $cx, 262, 16, self::INK, 'serif', true);
            $c->dashed($x + 18, 300, $x + 162, 300, self::ACCENT, 6);
            $c->centeredText('drop the grind here', $cx, 312, 11, self::SAGE, 'sans', false, true);
        }
        $c->text('Grind sizes:', 24, 380, 14, self::SAGE, 'serif', false, true);

        return $c;
    }

    /** One 960x540 frame of an editorial film. */
    public static function filmFrame(string $kicker, string $title, string $body = '', string $variant = 'plain'): Canvas
    {
        $c = new Canvas(960, 540, $variant === 'dark' ? self::INK : self::PAPER);
        $ink = $variant === 'dark' ? self::PAPER : self::INK;
        $c->speckle(900, $ink, crc32($title) % 1000, 1.2, 112);
        if ($variant === 'hills') {
            for ($k = 0; $k < 5; $k++) {
                $pts = [];
                for ($x = 0; $x <= 960; $x += 40) {
                    $pts[] = [$x, 330 + $k * 40 + sin($x / (120 + $k * 30) + $k) * (40 - $k * 5)];
                }
                $pts[] = [960, 540];
                $pts[] = [0, 540];
                $c->polygon($pts, ['#A9B59A', '#93A284', self::SAGE, '#5F6E55', '#4C5A44'][$k]);
            }
            $c->circle(760, 210, 120, '#F2C27B', 30);
            $c->circle(760, 210, 80, '#F2C27B');
        } elseif ($variant === 'branch') {
            self::cherryBranch($c, 640, 330, 0.8);
        }
        $c->text(mb_strtoupper($kicker), 60, 60, 14, self::ACCENT, 'sans', true);
        $y = $c->paragraph($title, 60, 90, 560, 40, $ink, 'serif', true, 1.1);
        if ($body !== '') {
            $c->paragraph($body, 60, $y + 16, 520, 18, $ink, 'serif', false, 1.4, true);
        }
        $c->line(60, 490, 900, 490, $variant === 'dark' ? '#5A4636' : self::RULE, 1);
        $c->text('The Coffee Atlas', 60, 500, 12, $variant === 'dark' ? self::RULE : self::SAGE, 'sans', true);

        return $c;
    }

    // ------------------------------------------------------------- printables

    private static function pdfStyle(): string
    {
        return '<style>
            @page { margin: 22mm 18mm; }
            body { font-family: "DejaVu Serif", serif; color: ' . self::INK . '; font-size: 11pt; line-height: 1.45; }
            h1 { font-size: 30pt; margin: 0 0 4pt; } h2 { font-size: 16pt; margin: 0 0 6pt; color: ' . self::ACCENT . '; }
            .kicker { font-family: "DejaVu Sans", sans-serif; font-size: 8pt; letter-spacing: 2pt; color: ' . self::SAGE . '; text-transform: uppercase; }
            .page { page-break-after: always; }
            .rule { border-top: 1.5pt solid ' . self::INK . '; margin: 8pt 0 12pt; }
            table { width: 100%; border-collapse: collapse; font-family: "DejaVu Sans", sans-serif; font-size: 8.5pt; }
            th { text-align: left; border-bottom: 1pt solid ' . self::INK . '; padding: 4pt 3pt; }
            td { border-bottom: 0.5pt solid ' . self::RULE . '; padding: 7pt 3pt; height: 12pt; }
            .box { border: 0.6pt solid ' . self::RULE . '; padding: 8pt; margin-bottom: 8pt; }
            .muted { color: ' . self::SAGE . '; }
            .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-family: "DejaVu Sans"; font-size: 7pt; color: ' . self::SAGE . '; }
        </style>';
    }

    public static function logbookHtml(): string
    {
        $html = '<html><head>' . self::pdfStyle() . '</head><body><div class="footer">The Coffee Atlas · Roaster\'s logbook · print double-sided</div>';
        $html .= '<div class="page"><div class="kicker">The Coffee Atlas · Chapter III</div><h1>Roaster\'s logbook</h1><div class="rule"></div>
            <p style="font-size:14pt"><i>Every good roaster keeps notes. Write down every batch, taste it two days later, and change one variable at a time.</i></p>
            <div class="box"><b>Roaster</b> ______________________ &nbsp; <b>Machine</b> ______________________ &nbsp; <b>Year</b> ______</div>
            <h2>How to use this logbook</h2>
            <ol><li>Fill in the green coffee page once per lot: origin, process, moisture, density.</li>
            <li>Log every batch on a batch sheet: charge, turning point, dry end, first crack, drop, weight loss.</li>
            <li>Calculate the <b>development time ratio</b> = time from first crack to drop ÷ total roast time. Aim for 16–25 % on filter roasts.</li>
            <li>Calculate <b>weight loss</b> = (green weight − roasted weight) ÷ green weight. Light roasts lose 12–14 %, medium 14–16 %, dark 16–20 %.</li>
            <li>Cup the batch after 24–72 hours of rest and score it on the cupping page.</li></ol>
            <h2>Reference points</h2>
            <table><tr><th>Event</th><th>Typical bean temperature</th><th>What you notice</th></tr>
            <tr><td>Turning point</td><td>85–100 °C</td><td>The probe stops falling and starts to rise</td></tr>
            <tr><td>Dry end (yellowing)</td><td>145–155 °C</td><td>Grassy smell turns to hay and bread</td></tr>
            <tr><td>First crack</td><td>193–200 °C</td><td>Audible popping, like popcorn</td></tr>
            <tr><td>Second crack</td><td>220–228 °C</td><td>Quieter crackling, oils on the surface</td></tr></table></div>';
        $html .= '<div class="page"><div class="kicker">Green coffee</div><h2>Lot record</h2><table><tr><th style="width:28%">Field</th><th>Lot A</th><th>Lot B</th><th>Lot C</th></tr>';
        foreach (['Origin / farm', 'Variety', 'Process', 'Altitude (m)', 'Harvest', 'Moisture (%)', 'Density (g/l)', 'Screen size', 'Supplier', 'Price per kg', 'First impressions'] as $f) {
            $html .= "<tr><td>$f</td><td></td><td></td><td></td></tr>";
        }
        $html .= '</table></div>';
        for ($p = 1; $p <= 4; $p++) {
            $html .= '<div class="page"><div class="kicker">Batch sheets · page ' . $p . ' of 4</div><h2>Batches</h2><table><tr><th>#</th><th>Date</th><th>Lot</th><th>Green g</th><th>Charge °C</th><th>TP</th><th>Dry end</th><th>1st crack</th><th>Drop</th><th>Dev. %</th><th>Roasted g</th><th>Loss %</th></tr>';
            for ($r = 1; $r <= 14; $r++) {
                $html .= '<tr><td>' . (($p - 1) * 14 + $r) . '</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
            }
            $html .= '</table><p class="muted">Times as m:ss · temperatures from the bean probe · note any change of gas or airflow next to the batch number.</p></div>';
        }
        $html .= '<div class="page"><div class="kicker">Cupping</div><h2>Cupping scores</h2><table><tr><th>Batch</th><th>Rest (days)</th><th>Fragrance</th><th>Flavour</th><th>Acidity</th><th>Body</th><th>Sweetness</th><th>Aftertaste</th><th>Total</th><th>Descriptors</th></tr>';
        for ($r = 1; $r <= 14; $r++) {
            $html .= '<tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
        }
        $html .= '</table></div>';
        $html .= '<div><div class="kicker">Notes</div><h2>What I changed and why</h2>';
        for ($r = 1; $r <= 22; $r++) {
            $html .= '<div style="border-bottom:0.5pt solid ' . self::RULE . '; height:22pt"></div>';
        }
        $html .= '</div></body></html>';

        return $html;
    }

    public static function recipeCardHtml(): string
    {
        return '<html><head>' . self::pdfStyle() . '</head><body>
            <div class="kicker">The Coffee Atlas · Chapter VI</div><h1>My signature cup</h1><div class="rule"></div>
            <table><tr><th style="width:30%">Coffee</th><td>Origin, farm, process, roast date</td></tr>
            <tr><th>Brewer</th><td></td></tr><tr><th>Dose (g)</th><td></td></tr><tr><th>Water (g) and ratio</th><td></td></tr>
            <tr><th>Water temperature</th><td></td></tr><tr><th>Grind setting</th><td></td></tr><tr><th>Pours (time → total water)</th><td style="height:60pt"></td></tr>
            <tr><th>Total brew time</th><td></td></tr><tr><th>TDS / extraction (optional)</th><td></td></tr></table>
            <h2 style="margin-top:14pt">Three variations</h2>
            <table><tr><th>Variation</th><th>What I changed</th><th>Tasting notes</th><th>Score /10</th></tr>
            <tr><td>A</td><td></td><td></td><td></td></tr><tr><td>B</td><td></td><td></td><td></td></tr><tr><td>C</td><td></td><td></td><td></td></tr></table>
            <h2 style="margin-top:14pt">Why this is my cup</h2><div class="box" style="height:110pt"></div>
            <p class="muted">Attach a photo of each variation. Submit this card as PDF or image in the project "Design your recipe".</p>
        </body></html>';
    }
}
