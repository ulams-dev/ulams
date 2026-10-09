<?php

namespace Database\Seeders\Demo\Art;

use Database\Seeders\Demo\Support\Canvas;

/**
 * Illustrations and printables for "Night Sky Explorers": deep night blue,
 * chunky rounded shapes, playful accents and Orbi the robot guide.
 */
class NightSkyArt
{
    public const NIGHT = '#13153A';
    public const DEEP = '#0D0F2B';
    public const YELLOW = '#FFD23F';
    public const CORAL = '#FF6B6B';
    public const MINT = '#3DDC97';
    public const LILAC = '#9B8CFF';
    public const WHITE = '#FFFFFF';

    private static function sky(int $w, int $h, int $seed = 7): Canvas
    {
        $c = new Canvas($w, $h, self::NIGHT);
        $c->speckle((int) ($w * $h / 900), self::WHITE, $seed, 2.4, 30);
        $c->speckle((int) ($w * $h / 9000), self::YELLOW, $seed + 1, 3.0, 10);

        return $c;
    }

    /** Orbi the robot guide; ($x, $y) is the centre of the head. */
    public static function orbi(Canvas $c, float $x, float $y, float $s = 1.0, bool $waving = true): void
    {
        $c->line($x, $y - 70 * $s, $x, $y - 110 * $s, self::WHITE, (int) max(3, 6 * $s));
        $c->circle($x, $y - 116 * $s, 28 * $s, self::CORAL);
        $c->roundRect($x - 80 * $s, $y - 70 * $s, 160 * $s, 130 * $s, 40 * $s, self::WHITE);
        $c->roundRect($x - 64 * $s, $y - 54 * $s, 128 * $s, 98 * $s, 30 * $s, self::LILAC);
        $c->circle($x - 28 * $s, $y - 8 * $s, 34 * $s, self::WHITE);
        $c->circle($x + 28 * $s, $y - 8 * $s, 34 * $s, self::WHITE);
        $c->circle($x - 24 * $s, $y - 6 * $s, 16 * $s, self::NIGHT);
        $c->circle($x + 32 * $s, $y - 6 * $s, 16 * $s, self::NIGHT);
        $c->arc($x, $y + 20 * $s, 50 * $s, 30 * $s, 0, 180, self::NIGHT);
        $c->roundRect($x - 60 * $s, $y + 66 * $s, 120 * $s, 110 * $s, 30 * $s, self::WHITE);
        $c->circle($x, $y + 120 * $s, 40 * $s, self::YELLOW);
        $c->text('★', $x - 11 * $s, $y + 104 * $s, 22 * $s, self::NIGHT, 'sans', true);
        // arms
        $c->line($x - 60 * $s, $y + 90 * $s, $x - 105 * $s, $y + 140 * $s, self::WHITE, (int) max(4, 14 * $s));
        if ($waving) {
            $c->line($x + 60 * $s, $y + 90 * $s, $x + 110 * $s, $y + 30 * $s, self::WHITE, (int) max(4, 14 * $s));
            $c->circle($x + 114 * $s, $y + 22 * $s, 30 * $s, self::MINT);
        } else {
            $c->line($x + 60 * $s, $y + 90 * $s, $x + 105 * $s, $y + 140 * $s, self::WHITE, (int) max(4, 14 * $s));
        }
    }

    public static function planet(Canvas $c, float $x, float $y, float $d, string $color, ?string $ring = null, ?string $band = null): void
    {
        if ($ring) {
            $c->ellipse($x, $y, $d * 2.1, $d * 0.55, $ring);
            $c->ellipse($x, $y, $d * 1.6, $d * 0.36, self::NIGHT);
        }
        $c->circle($x, $y, $d, $color);
        if ($band) {
            for ($i = -2; $i <= 2; $i++) {
                $c->ellipse($x, $y + $i * $d * 0.16, $d * 0.9 * sqrt(1 - ($i * 0.16 * 2) ** 2), $d * 0.07, $band, 40);
            }
        }
        $c->circle($x - $d * 0.2, $y - $d * 0.2, $d * 0.3, self::WHITE, 105);
    }

    public static function cover(string $kicker, string $title, string $subtitle, int $w = 1600, int $h = 900): Canvas
    {
        $c = self::sky($w, $h);
        $s = $w / 1600;
        self::planet($c, 1280 * $s, 230 * $s, 220 * $s, '#E8B25C', '#F3D59A', '#C98B3A');
        self::planet($c, 1460 * $s, 620 * $s, 90 * $s, self::CORAL, null, '#D9534F');
        self::planet($c, 980 * $s, 120 * $s, 60 * $s, self::MINT);
        self::orbi($c, 1150 * $s, 560 * $s, 1.25 * $s);
        $c->roundRect(80 * $s, 120 * $s, $c->textWidth($kicker, 18 * $s, 'sans', true) + 48 * $s, 52 * $s, 26 * $s, self::YELLOW);
        $c->text($kicker, 104 * $s, 132 * $s, 18 * $s, self::NIGHT, 'sans', true);
        $y = $c->paragraph($title, 80 * $s, 220 * $s, 760 * $s, 66 * $s, self::WHITE, 'sans', true, 1.05);
        $c->paragraph($subtitle, 80 * $s, $y + 20 * $s, 700 * $s, 26 * $s, self::YELLOW, 'sans', false, 1.35);
        $c->text('with Dr. Ada Kowalczyk and Orbi', 80 * $s, $h - 110 * $s, 20 * $s, self::LILAC, 'sans', true);

        return $c;
    }

    /**
     * Star chart of the sky over Kraków (50° N) on a mid-October evening at 21:00.
     * Alt-azimuth projection: centre = zenith, edge = horizon.
     */
    public static function starChart(): Canvas
    {
        $c = new Canvas(1400, 1400, self::NIGHT);
        $cx = 700;
        $cy = 740;
        $r = 560;
        $c->circle($cx, $cy, $r * 2 + 16, self::LILAC);
        $c->circle($cx, $cy, $r * 2, self::DEEP);
        $c->speckle(420, self::WHITE, 21, 1.6, 60);
        // repaint outside the horizon circle
        $c->rect(0, 0, 1400, 150, self::NIGHT);
        $c->text('Your sky tonight', 60, 36, 46, self::WHITE, 'sans', true);
        $c->text('Kraków · mid-October · 21:00 · hold the chart above your head, N pointing north', 60, 104, 20, self::YELLOW, 'sans');
        // alt-az to xy: azimuth 0 = north at top, east to the left (as seen looking up)
        $p = fn (float $alt, float $az) => [$cx - sin(deg2rad($az)) * $r * (90 - $alt) / 90, $cy - cos(deg2rad($az)) * $r * (90 - $alt) / 90];
        foreach ([30, 60] as $alt) {
            $c->ring($cx, $cy, $r * 2 * (90 - $alt) / 90, $r * 2 * (90 - $alt) / 90, '#2A2D66', 1);
        }
        $constellations = [
            'Big Dipper' => [[[26, 335], [22, 343], [19, 352], [21, 0], [20, 8], [22, 14], [27, 18]], [[21, 0], [23, 350]]],
            'Cassiopeia' => [[[66, 32], [70, 41], [65, 48], [68, 57], [62, 63]]],
            'Cygnus' => [[[58, 290], [50, 270], [42, 250]], [[52, 300], [50, 270], [47, 238]]],
            'Lyra' => [[[40, 288], [37, 283], [34, 286], [36, 291], [40, 288]]],
            'Pegasus' => [[[58, 150], [50, 140], [43, 163], [50, 175], [58, 150]]],
            'Andromeda' => [[[58, 150], [64, 120], [66, 100]]],
            'Perseus' => [[[46, 52], [42, 60], [36, 64]]],
        ];
        foreach ($constellations as $name => $paths) {
            foreach ($paths as $path) {
                $points = array_map(fn ($a) => $p($a[0], $a[1]), $path);
                $c->polyline($points, self::MINT, 3);
                foreach ($points as [$x, $y]) {
                    $c->circle($x, $y, 14, self::WHITE);
                }
            }
            $anchor = $name === 'Andromeda' ? end($paths[0]) : $paths[0][0];
            [$lx, $ly] = $p($anchor[0] - 6, $anchor[1]);
            $c->text($name, $lx - 40, $ly + 6, 18, self::YELLOW, 'sans', true);
        }
        // North Star and Moon, Saturn
        [$nx, $ny] = $p(50, 0);
        $c->circle($nx, $ny, 22, self::YELLOW);
        $c->text('North Star (Polaris)', $nx + 18, $ny - 12, 17, self::YELLOW, 'sans', true);
        [$sx, $sy] = $p(28, 160);
        self::planet($c, $sx, $sy, 26, '#E8B25C', '#F3D59A');
        $c->text('Saturn', $sx + 24, $sy - 10, 17, self::CORAL, 'sans', true);
        [$mx, $my] = $p(22, 115);
        $c->circle($mx, $my, 54, '#F4F1DE');
        $c->circle($mx + 14, $my - 6, 46, self::DEEP);
        $c->text('Moon', $mx - 20, $my + 34, 17, self::WHITE, 'sans', true);
        foreach (['N' => 0, 'E' => 90, 'S' => 180, 'W' => 270] as $label => $az) {
            [$x, $y] = $p(-6, $az);
            $c->circle($x, $y, 58, self::YELLOW);
            $c->centeredText($label, $x, $y - 16, 24, self::NIGHT, 'sans', true);
        }
        $c->text('Zenith = straight above you', $cx - 130, $cy + 12, 14, self::LILAC, 'sans');
        $c->circle($cx, $cy, 8, self::LILAC);

        return $c;
    }

    /** Background for the Moon-phase drag and drop (800x500). */
    public static function moonOrbit(): Canvas
    {
        $c = self::sky(800, 500, 3);
        // Sun on the left with rays
        $c->circle(-70, 250, 200, self::YELLOW);
        for ($i = 0; $i < 6; $i++) {
            $c->line(48, 150 + $i * 40, 120, 150 + $i * 40, self::YELLOW, 4);
        }
        $c->text('Sunlight', 18, 460, 18, self::YELLOW, 'sans', true);
        $cx = 390;
        $cy = 250;
        $rx = 200;
        $ry = 170;
        $c->ring($cx, $cy, $rx * 2, $ry * 2, self::LILAC, 3);
        self::planet($c, $cx, $cy, 90, '#3B82F6');
        $c->ellipse($cx - 10, $cy - 8, 40, 26, self::MINT);
        $c->ellipse($cx + 18, $cy + 18, 30, 18, self::MINT);
        $c->text('Earth', $cx - 26, $cy + 50, 16, self::WHITE, 'sans', true);
        // eight Moon positions; the half facing the Sun (left) is lit
        for ($k = 0; $k < 8; $k++) {
            $a = deg2rad(180 + $k * 45);
            $x = $cx + cos($a) * $rx;
            $y = $cy - sin($a) * $ry;
            $c->circle($x, $y, 44, '#4A4E7A');
            $c->arc($x, $y, 44, 44, 90, 270, '#F4F1DE');
        }
        $c->text('↺ the Moon travels this way around Earth', 150, 18, 15, self::LILAC, 'sans', true);
        $c->roundRect(612, 30, 178, 440, 24, self::DEEP);
        $c->centeredText('Moon phases', 701, 44, 15, self::YELLOW, 'sans', true);

        return $c;
    }

    /** Drop zone centres (percent of 800x500) for new, first quarter, full, last quarter. */
    public static function moonZones(): array
    {
        $cx = 390;
        $cy = 250;
        $rx = 200;
        $ry = 170;
        $zones = [];
        foreach ([0, 2, 4, 6] as $k) {
            $a = deg2rad(180 + $k * 45);
            $zones[] = [($cx + cos($a) * $rx) / 8, ($cy - sin($a) * $ry) / 5];
        }

        return $zones;
    }

    public static function planetCard(string $name, string $color, ?string $ring, ?string $band, string $fact): Canvas
    {
        $c = self::sky(1200, 700, crc32($name) % 100);
        self::planet($c, 360, 350, 420, $color, $ring, $band);
        $c->roundRect(700, 180, 440, 340, 40, self::WHITE);
        $c->text($name, 740, 214, 44, self::NIGHT, 'sans', true);
        $c->paragraph($fact, 740, 292, 370, 22, self::NIGHT, 'sans', false, 1.4);
        $c->roundRect(740, 450, 150, 44, 22, self::YELLOW);
        $c->text('Fun fact!', 760, 460, 18, self::NIGHT, 'sans', true);

        return $c;
    }

    public static function scale(): Canvas
    {
        $c = self::sky(1400, 600, 9);
        $c->text('How far does light travel?', 50, 30, 40, self::WHITE, 'sans', true);
        $c->text('Light covers 300,000 km every second', 50, 92, 22, self::YELLOW, 'sans', true);
        $stops = [
            [110, 'Moon', '1.3 light-seconds', '#F4F1DE', 40],
            [380, 'Sun', '8 light-minutes', self::YELLOW, 90],
            [680, 'Neptune', '4 light-hours', '#5B8DEF', 50],
            [960, 'Nearest star', '4.2 light-years', self::CORAL, 34],
            [1240, 'Andromeda galaxy', '2.5 million light-years', self::LILAC, 70],
        ];
        $c->dashed(60, 330, 1340, 330, self::LILAC, 10, 3);
        foreach ($stops as [$x, $name, $dist, $color, $d]) {
            $c->circle($x, 330, $d, $color);
            $c->centeredText($name, $x, 390, 20, self::WHITE, 'sans', true);
            $c->centeredText($dist, $x, 422, 17, self::MINT, 'sans');
        }
        $c->text('Earth', 30, 270, 16, self::WHITE, 'sans', true);
        $c->text('(not to scale: if it were, the Andromeda dot would be 400 times farther away than the Moon is from you)', 50, 520, 15, self::LILAC, 'sans');

        return $c;
    }

    public static function filmFrame(string $kicker, string $title, string $body = '', string $scene = 'orbi'): Canvas
    {
        $c = self::sky(960, 540, crc32($title) % 50);
        switch ($scene) {
            case 'orbi':
                self::orbi($c, 760, 300, 0.95);
                break;
            case 'nebula':
                foreach ([[700, 260, 360, self::LILAC], [760, 300, 260, self::CORAL], [660, 320, 240, '#5B8DEF'], [740, 220, 160, self::MINT]] as [$x, $y, $d, $col]) {
                    $c->circle($x, $y, $d, $col, 88);
                }
                $c->speckle(120, self::WHITE, 4, 3, 0);
                break;
            case 'star':
                $c->circle(740, 280, 300, self::YELLOW, 60);
                $c->circle(740, 280, 200, self::YELLOW);
                break;
            case 'giant':
                $c->circle(740, 280, 420, self::CORAL, 50);
                $c->circle(740, 280, 330, self::CORAL);
                break;
            case 'dwarf':
                $c->circle(740, 280, 200, self::LILAC, 90);
                $c->circle(740, 280, 46, self::WHITE);
                break;
            default:
                self::planet($c, 760, 280, 200, '#E8B25C', '#F3D59A', '#C98B3A');
        }
        $c->roundRect(40, 40, $c->textWidth($kicker, 15, 'sans', true) + 40, 36, 18, self::YELLOW);
        $c->text($kicker, 60, 49, 15, self::NIGHT, 'sans', true);
        $y = $c->paragraph($title, 40, 104, 520, 36, self::WHITE, 'sans', true, 1.1);
        if ($body !== '') {
            $c->paragraph($body, 40, $y + 14, 480, 18, self::YELLOW, 'sans', false, 1.4);
        }

        return $c;
    }

    // ------------------------------------------------------------- printables

    private static function pdfStyle(): string
    {
        return '<style>
            @page { margin: 14mm; }
            body { font-family: "DejaVu Sans", sans-serif; color: ' . self::NIGHT . '; font-size: 10pt; }
            h1 { font-size: 26pt; margin: 0; } .sub { color: #5B4FCF; font-size: 11pt; margin-bottom: 8pt; }
            table { width: 100%; border-collapse: separate; border-spacing: 5pt; }
            td { border: 2pt solid ' . self::LILAC . '; border-radius: 10pt; padding: 4pt; text-align: center; vertical-align: top; height: 95pt; width: 16%; }
            .day { font-weight: bold; font-size: 9pt; }
            .moon { width: 44pt; height: 44pt; border: 1.5pt dashed #9B8CFF; border-radius: 22pt; margin: 4pt auto; }
            .line { border-bottom: 0.8pt solid #C9C3F5; height: 11pt; margin: 2pt 4pt; }
            .tip { background: #FFF6D1; border: 2pt solid ' . self::YELLOW . '; border-radius: 10pt; padding: 6pt 10pt; margin-top: 6pt; font-size: 9.5pt; }
            .page { page-break-after: always; }
        </style>';
    }

    public static function moonDiaryHtml(): string
    {
        $html = '<html><head>' . self::pdfStyle() . '</head><body>';
        for ($page = 0; $page < 2; $page++) {
            $html .= '<div class="' . ($page === 0 ? 'page' : '') . '"><h1>My Moon diary</h1><div class="sub">Night Sky Explorers · Mission 2 · days ' . ($page * 15 + 1) . '–' . ($page * 15 + 15) . '</div><table>';
            for ($row = 0; $row < 3; $row++) {
                $html .= '<tr>';
                for ($col = 0; $col < 5; $col++) {
                    $day = $page * 15 + $row * 5 + $col + 1;
                    $html .= '<td><div class="day">Day ' . $day . '</div><div class="moon"></div><div class="line"></div><div class="line"></div></td>';
                }
                $html .= '</tr>';
            }
            $html .= '</table>';
            $html .= $page === 0
                ? '<div class="tip"><b>How to use it:</b> every evening, find the Moon and shade the dark part of the circle. Write the time and where you saw it (east, south, west). No Moon? Write "not visible" — that is data too!</div>'
                : '<div class="tip"><b>After 30 days:</b> colour the full Moon yellow and the new Moon dark blue. How many days from one full Moon to the next? (Hint: about 29 and a half!)</div>';
            $html .= '</div>';
        }

        return $html . '</body></html>';
    }

    public static function starMapWorksheetHtml(): string
    {
        return '<html><head>' . self::pdfStyle() . '</head><body>
            <h1>My star map</h1><div class="sub">Night Sky Explorers · Mission 7</div>
            <div style="width:170mm;height:170mm;border:3pt solid ' . self::LILAC . ';border-radius:85mm;margin:6pt auto;position:relative;text-align:center">
              <div style="position:absolute;top:-2mm;left:80mm;font-weight:bold">N</div>
              <div style="position:absolute;bottom:-2mm;left:80mm;font-weight:bold">S</div>
              <div style="position:absolute;top:80mm;left:2mm;font-weight:bold">E</div>
              <div style="position:absolute;top:80mm;right:2mm;font-weight:bold">W</div>
            </div>
            <div class="tip">1. Go outside with a grown-up. 2. Lie down or face north. 3. Draw the brightest stars as dots, then join them with lines. 4. Give your own constellation a name and write its story on the back!</div>
            <p>Name: ______________________ &nbsp; Date: __________ &nbsp; Place: ______________________</p>
        </body></html>';
    }
}
