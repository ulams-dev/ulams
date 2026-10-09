<?php

namespace Ulams\TemplatesPdf\Pdfme;

use InvalidArgumentException;

/**
 * Certificate templates shipped with the package (resources/pdfme), built by
 * api/pdf/scripts/certificate-templates.mjs. `default` is seeded as the default
 * CourseFinished certificate; the themed ones match the demo experiences and
 * can be assigned to their courses by the demo seeding:
 *
 *     CertificateTemplates::content('coffee') // pdfme JSON for the template's `content` section
 */
class CertificateTemplates
{
    public const DEFAULT = 'default';
    public const THEMES = ['default', 'coffee', 'oncall', 'nightsky', 'gravity', 'poland', 'ulam'];

    public static function path(string $theme = self::DEFAULT): string
    {
        if (!in_array($theme, self::THEMES, true)) {
            throw new InvalidArgumentException("Unknown certificate theme \"{$theme}\".");
        }

        return dirname(__DIR__, 2) . '/resources/pdfme/certificate-' . $theme . '.json';
    }

    /**
     * pdfme template as compact JSON.
     */
    public static function content(string $theme = self::DEFAULT): string
    {
        return json_encode(self::template($theme), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function template(string $theme = self::DEFAULT): array
    {
        return json_decode(file_get_contents(self::path($theme)), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * A4 portrait page with the learner name and the date, the starting point
     * for PDFs that are not course certificates.
     */
    public static function simpleDocument(): string
    {
        $text = fn (string $name, string $content, float $y, float $size) => [
            'name' => $name, 'type' => 'text', 'content' => $content,
            'position' => ['x' => 20, 'y' => $y], 'width' => 170, 'height' => $size / 2,
            'fontName' => 'NotoSans-Regular', 'fontSize' => $size, 'alignment' => 'left', 'verticalAlignment' => 'top',
            'lineHeight' => 1, 'characterSpacing' => 0, 'fontColor' => '#1F2937', 'backgroundColor' => '', 'opacity' => 1, 'rotate' => 0,
        ];

        return json_encode([
            'basePdf' => ['width' => 210, 'height' => 297, 'padding' => [0, 0, 0, 0]],
            'schemas' => [[
                $text('@VarUserName', 'Małgorzata Wiśniewska', 30, 24),
                $text('@VarToday', '08.10.2026', 48, 12),
            ]],
        ], JSON_UNESCAPED_UNICODE);
    }
}
