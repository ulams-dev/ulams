<?php

namespace Database\Seeders\Demo\Support;

use GdImage;
use RuntimeException;

/**
 * Small wrapper around GD used to draw the demo illustrations (title cards,
 * diagrams, charts). Colours are given as #RRGGBB strings, fonts by role
 * (serif, sans, mono) and weight, all from the DejaVu family that ships
 * with dompdf, so no font has to be added to the repository.
 */
class Canvas
{
    private GdImage $image;
    private int $width;
    private int $height;
    /** @var array<string, int> */
    private array $colors = [];

    public function __construct(int $width, int $height, string $background)
    {
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw new RuntimeException('Cannot create image');
        }
        $this->image = $image;
        $this->width = $width;
        $this->height = $height;
        imagealphablending($this->image, true);
        // no imageantialias(): it disables line thickness in GD
        imagefilledrectangle($this->image, 0, 0, $width, $height, $this->color($background));
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function gd(): GdImage
    {
        return $this->image;
    }

    public static function font(string $family = 'sans', bool $bold = false, bool $italic = false): string
    {
        $map = [
            'serif' => ['DejaVuSerif', 'DejaVuSerif-Bold', 'DejaVuSerif-Italic', 'DejaVuSerif-BoldItalic'],
            'sans' => ['DejaVuSans', 'DejaVuSans-Bold', 'DejaVuSans-Oblique', 'DejaVuSans-BoldOblique'],
            'mono' => ['DejaVuSansMono', 'DejaVuSansMono-Bold', 'DejaVuSansMono-Oblique', 'DejaVuSansMono-BoldOblique'],
        ];
        $index = ($bold ? 1 : 0) + ($italic ? 2 : 0);
        $name = $map[$family][$index] ?? $map['sans'][0];

        return base_path('vendor/dompdf/dompdf/lib/fonts/' . $name . '.ttf');
    }

    public function color(string $hex, int $alpha = 0): int
    {
        $key = $hex . '/' . $alpha;
        if (!isset($this->colors[$key])) {
            $hex = ltrim($hex, '#');
            $this->colors[$key] = imagecolorallocatealpha(
                $this->image,
                hexdec(substr($hex, 0, 2)),
                hexdec(substr($hex, 2, 2)),
                hexdec(substr($hex, 4, 2)),
                $alpha
            );
        }

        return $this->colors[$key];
    }

    public function rect(float $x, float $y, float $w, float $h, string $fill, int $alpha = 0): self
    {
        imagefilledrectangle($this->image, (int) $x, (int) $y, (int) ($x + $w), (int) ($y + $h), $this->color($fill, $alpha));

        return $this;
    }

    public function frame(float $x, float $y, float $w, float $h, string $stroke, int $thickness = 1): self
    {
        imagesetthickness($this->image, $thickness);
        imagerectangle($this->image, (int) $x, (int) $y, (int) ($x + $w), (int) ($y + $h), $this->color($stroke));
        imagesetthickness($this->image, 1);

        return $this;
    }

    public function roundRect(float $x, float $y, float $w, float $h, float $r, string $fill, int $alpha = 0): self
    {
        $c = $this->color($fill, $alpha);
        $r = (int) min($r, $w / 2, $h / 2);
        [$x, $y, $w, $h] = [(int) $x, (int) $y, (int) $w, (int) $h];
        imagefilledrectangle($this->image, $x + $r, $y, $x + $w - $r, $y + $h, $c);
        imagefilledrectangle($this->image, $x, $y + $r, $x + $w, $y + $h - $r, $c);
        foreach ([[$x + $r, $y + $r], [$x + $w - $r, $y + $r], [$x + $r, $y + $h - $r], [$x + $w - $r, $y + $h - $r]] as [$cx, $cy]) {
            imagefilledellipse($this->image, $cx, $cy, $r * 2, $r * 2, $c);
        }

        return $this;
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $stroke, int $thickness = 1, int $alpha = 0): self
    {
        imagesetthickness($this->image, $thickness);
        imageline($this->image, (int) $x1, (int) $y1, (int) $x2, (int) $y2, $this->color($stroke, $alpha));
        imagesetthickness($this->image, 1);

        return $this;
    }

    public function dashed(float $x1, float $y1, float $x2, float $y2, string $stroke, int $dash = 8, int $thickness = 1): self
    {
        $length = hypot($x2 - $x1, $y2 - $y1);
        if ($length == 0) {
            return $this;
        }
        $steps = (int) floor($length / ($dash * 2));
        for ($i = 0; $i <= $steps; $i++) {
            $t1 = ($i * 2 * $dash) / $length;
            $t2 = min(1, (($i * 2 + 1) * $dash) / $length);
            $this->line($x1 + ($x2 - $x1) * $t1, $y1 + ($y2 - $y1) * $t1, $x1 + ($x2 - $x1) * $t2, $y1 + ($y2 - $y1) * $t2, $stroke, $thickness);
        }

        return $this;
    }

    /** @param array<int, array{0: float, 1: float}> $points */
    public function polyline(array $points, string $stroke, int $thickness = 2, int $alpha = 0): self
    {
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $this->line($points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1], $stroke, $thickness, $alpha);
        }

        return $this;
    }

    /** @param array<int, array{0: float, 1: float}> $points */
    public function polygon(array $points, string $fill, int $alpha = 0): self
    {
        if (count($points) < 3) {
            return $this;
        }
        $flat = [];
        foreach ($points as [$x, $y]) {
            $flat[] = (int) $x;
            $flat[] = (int) $y;
        }
        imagefilledpolygon($this->image, $flat, $this->color($fill, $alpha));

        return $this;
    }

    public function circle(float $cx, float $cy, float $diameter, string $fill, int $alpha = 0): self
    {
        imagefilledellipse($this->image, (int) $cx, (int) $cy, (int) $diameter, (int) $diameter, $this->color($fill, $alpha));

        return $this;
    }

    public function ellipse(float $cx, float $cy, float $w, float $h, string $fill, int $alpha = 0): self
    {
        imagefilledellipse($this->image, (int) $cx, (int) $cy, (int) $w, (int) $h, $this->color($fill, $alpha));

        return $this;
    }

    public function ring(float $cx, float $cy, float $w, float $h, string $stroke, int $thickness = 2): self
    {
        imagesetthickness($this->image, $thickness);
        imagearc($this->image, (int) $cx, (int) $cy, (int) $w, (int) $h, 0, 360, $this->color($stroke));
        imagesetthickness($this->image, 1);

        return $this;
    }

    public function arc(float $cx, float $cy, float $w, float $h, int $start, int $end, string $fill, int $alpha = 0): self
    {
        imagefilledarc($this->image, (int) $cx, (int) $cy, (int) $w, (int) $h, $start, $end, $this->color($fill, $alpha), IMG_ARC_PIE);

        return $this;
    }

    /**
     * Draws text with its top-left corner at ($x, $y). Returns the text width.
     */
    public function text(string $text, float $x, float $y, float $size, string $color, string $font = 'sans', bool $bold = false, bool $italic = false, float $angle = 0): float
    {
        $file = self::font($font, $bold, $italic);
        $box = imagettfbbox($size, 0, $file, $text);
        $ascent = -$box[7];
        imagettftext($this->image, $size, $angle, (int) $x, (int) ($y + $ascent), $this->color($color), $file, $text);

        return $box[2] - $box[0];
    }

    public function textWidth(string $text, float $size, string $font = 'sans', bool $bold = false, bool $italic = false): float
    {
        $box = imagettfbbox($size, 0, self::font($font, $bold, $italic), $text);

        return $box[2] - $box[0];
    }

    public function centeredText(string $text, float $cx, float $y, float $size, string $color, string $font = 'sans', bool $bold = false, bool $italic = false): self
    {
        $w = $this->textWidth($text, $size, $font, $bold, $italic);
        $this->text($text, $cx - $w / 2, $y, $size, $color, $font, $bold, $italic);

        return $this;
    }

    /**
     * Wraps text to a maximum width; returns the y coordinate under the last line.
     */
    public function paragraph(string $text, float $x, float $y, float $maxWidth, float $size, string $color, string $font = 'sans', bool $bold = false, float $lineHeight = 1.45, bool $italic = false): float
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($line !== '' && $this->textWidth($candidate, $size, $font, $bold, $italic) > $maxWidth) {
                $this->text($line, $x, $y, $size, $color, $font, $bold, $italic);
                $y += $size * $lineHeight * 1.33;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') {
            $this->text($line, $x, $y, $size, $color, $font, $bold, $italic);
            $y += $size * $lineHeight * 1.33;
        }

        return $y;
    }

    /** Deterministic pseudo-random grain or star field. */
    public function speckle(int $count, string $color, int $seed, float $maxSize = 2, int $alpha = 60): self
    {
        mt_srand($seed);
        for ($i = 0; $i < $count; $i++) {
            $size = 1 + mt_rand(0, (int) ($maxSize * 10)) / 10;
            $this->circle(mt_rand(0, $this->width), mt_rand(0, $this->height), $size, $color, $alpha);
        }
        mt_srand();

        return $this;
    }

    public function savePng(string $path): string
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        imagesavealpha($this->image, true);
        imagepng($this->image, $path, 9);

        return $path;
    }

    public function saveJpeg(string $path, int $quality = 82): string
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        imagejpeg($this->image, $path, $quality);

        return $path;
    }
}
