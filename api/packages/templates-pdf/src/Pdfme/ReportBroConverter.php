<?php

namespace Ulams\TemplatesPdf\Pdfme;

/**
 * Best-effort conversion of a ReportBro report (the PDF designer used before
 * pdfme) into a pdfme template. ReportBro layouts cannot be converted
 * reliably, so only the simple elements are carried over:
 *
 * - text: a text that is exactly one parameter ("${VarUserName}") becomes an
 *   editable field named after the variable ("@VarUserName"); any other text
 *   becomes read-only text, keeping "${Var}" placeholders (filled at render);
 * - line;
 * - image embedded as a PNG/JPEG data URI (read-only image).
 *
 * Everything else (tables, frames, sections, barcodes, rich text, conditional
 * styles, expressions) is dropped and reported, so converted templates must be
 * reviewed in the designer.
 */
class ReportBroConverter
{
    public const PT_TO_MM = 0.352778;

    private const PAGE_SIZES_PT = [
        'A4' => [595, 842],
        'A5' => [420, 595],
        'LETTER' => [612, 792],
    ];

    /**
     * @return array{template: array, converted: int, dropped: string[]}
     */
    public function convert(array $report): array
    {
        $props = $report['documentProperties'] ?? [];
        [$widthPt, $heightPt] = $this->pageSize($props);

        $marginLeft = $this->number($props['marginLeft'] ?? null, 20);
        $marginTop = $this->number($props['marginTop'] ?? null, 20);
        $headerHeight = !empty($props['header']) ? $this->number($props['headerSize'] ?? null, 80) : 0;

        $fields = [];
        $dropped = [];
        $usedNames = [];

        foreach ($report['docElements'] ?? [] as $element) {
            $type = $element['elementType'] ?? 'unknown';
            $container = (string) ($element['containerId'] ?? '0_content');
            $offsetY = match (true) {
                str_ends_with($container, '_header') => $marginTop,
                str_ends_with($container, '_content') => $marginTop + $headerHeight,
                default => null,
            };
            if ($offsetY === null) {
                // elements inside frames/sections/footers have positions relative to their container
                $dropped[] = "{$type} #" . ($element['id'] ?? '?') . ' (inside a container)';
                continue;
            }

            $x = $this->mm($this->number($element['x'] ?? 0, 0) + $marginLeft);
            $y = $this->mm($this->number($element['y'] ?? 0, 0) + $offsetY);
            $width = $this->mm($this->number($element['width'] ?? 0, 0));
            $height = $this->mm($this->number($element['height'] ?? 0, 0));
            $id = $element['id'] ?? count($fields);

            switch ($type) {
                case 'text':
                    if (!empty($element['richText']) || !empty($element['eval'])) {
                        $dropped[] = "text #{$id} (rich text or expression)";
                        break;
                    }
                    $fields[] = $this->text($element, $id, $x, $y, $width, $height, $usedNames);
                    break;
                case 'line':
                    $fields[] = [
                        'name' => 'line' . $id,
                        'type' => 'line',
                        'position' => ['x' => $x, 'y' => $y],
                        'width' => max($width, 1),
                        'height' => max($height, 0.2),
                        'rotate' => 0,
                        'opacity' => 1,
                        'color' => $this->color($element['color'] ?? null, '#000000'),
                        'readOnly' => true,
                    ];
                    break;
                case 'image':
                    $data = (string) ($element['image'] ?? '');
                    if (!preg_match('#^data:image/(png|jpe?g);base64,#', $data)) {
                        $dropped[] = "image #{$id} (not an embedded PNG/JPEG)";
                        break;
                    }
                    $fields[] = [
                        'name' => 'image' . $id,
                        'type' => 'image',
                        'content' => $data,
                        'position' => ['x' => $x, 'y' => $y],
                        'width' => max($width, 1),
                        'height' => max($height, 1),
                        'rotate' => 0,
                        'opacity' => 1,
                        'readOnly' => true,
                    ];
                    break;
                default:
                    $dropped[] = "{$type} #{$id}";
            }
        }

        return [
            'template' => [
                'basePdf' => [
                    'width' => $this->mm($widthPt),
                    'height' => $this->mm($heightPt),
                    'padding' => [0, 0, 0, 0],
                ],
                'schemas' => [$fields],
            ],
            'converted' => count($fields),
            'dropped' => $dropped,
        ];
    }

    private function text(array $element, mixed $id, float $x, float $y, float $width, float $height, array &$usedNames): array
    {
        $content = (string) ($element['content'] ?? '');
        $variable = preg_match('/^\s*\$\{([A-Za-z0-9_]+)\}\s*$/', $content, $m) ? '@' . $m[1] : null;
        $name = $variable && !in_array($variable, $usedNames, true) ? $variable : 'text' . $id;
        $usedNames[] = $name;

        $field = [
            'name' => $name,
            'type' => 'text',
            'content' => $variable ? $m[1] : $content,
            'position' => ['x' => $x, 'y' => $y],
            'width' => max($width, 1),
            'height' => max($height, 1),
            'rotate' => 0,
            'alignment' => $this->oneOf($element['horizontalAlignment'] ?? null, ['left', 'center', 'right', 'justify'], 'left'),
            'verticalAlignment' => $this->oneOf($element['verticalAlignment'] ?? null, ['top', 'middle', 'bottom'], 'top'),
            'fontSize' => $this->number($element['fontSize'] ?? null, 12),
            'lineHeight' => max($this->number($element['lineSpacing'] ?? null, 1), 1),
            'characterSpacing' => 0,
            'fontColor' => $this->color($element['textColor'] ?? null, '#000000'),
            'fontName' => $this->font((string) ($element['font'] ?? ''), !empty($element['bold'])),
            'backgroundColor' => $this->color($element['backgroundColor'] ?? null, ''),
            'opacity' => 1,
            'strikethrough' => !empty($element['strikethrough']),
            'underline' => !empty($element['underline']),
        ];
        if (!$variable || $name !== $variable) {
            $field['readOnly'] = true;
            if ($variable) {
                $field['content'] = $content;
            }
        }

        return $field;
    }

    private function pageSize(array $props): array
    {
        $format = strtoupper((string) ($props['pageFormat'] ?? 'A4'));
        if ($format === 'USER_DEFINED') {
            $unitToPt = ($props['unit'] ?? 'mm') === 'inch' ? 72 : 1 / self::PT_TO_MM;
            $size = [
                $this->number($props['pageWidth'] ?? null, 210) * $unitToPt,
                $this->number($props['pageHeight'] ?? null, 297) * $unitToPt,
            ];
        } else {
            $size = self::PAGE_SIZES_PT[$format] ?? self::PAGE_SIZES_PT['A4'];
        }
        if (($props['orientation'] ?? 'portrait') === 'landscape') {
            rsort($size);
            return $size;
        }
        sort($size);

        return $size;
    }

    private function font(string $font, bool $bold): string
    {
        return match (true) {
            in_array(strtolower($font), ['times', 'times-roman', 'serif', 'tangerine'], true) => 'PlayfairDisplay-Bold',
            in_array(strtolower($font), ['courier', 'monospace'], true) => 'JetBrainsMono-Regular',
            $bold => 'NotoSans-Bold',
            default => 'NotoSans-Regular',
        };
    }

    private function oneOf(mixed $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function color(mixed $value, string $default): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtoupper($value) : $default;
    }

    private function number(mixed $value, float $default): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }

    private function mm(float $pt): float
    {
        return round($pt * self::PT_TO_MM, 2);
    }
}
