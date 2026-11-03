<?php

namespace Ulams\TemplatesEmail\Accessibility;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Cheap accessibility checks of a rendered e-mail (HTML). The result is a list of warnings, never a
 * failure: authors decide what to do with them. Pure function of the HTML, no I/O.
 *
 * Each warning is ['code' => string, 'message' => string, 'count' => int] with at most one entry per code.
 */
class EmailA11yChecker
{
    public const MISSING_ALT = 'image-missing-alt';
    public const MISSING_LANG = 'missing-lang';
    public const MISSING_TITLE = 'missing-title';
    public const EMPTY_LINK = 'link-without-text';
    public const LAYOUT_TABLE = 'layout-table-without-role';
    public const LOW_CONTRAST = 'low-contrast';

    private const NAMED_COLORS = [
        'white' => [255, 255, 255], 'black' => [0, 0, 0], 'red' => [255, 0, 0], 'blue' => [0, 0, 255],
        'green' => [0, 128, 0], 'gray' => [128, 128, 128], 'grey' => [128, 128, 128], 'silver' => [192, 192, 192],
        'yellow' => [255, 255, 0], 'orange' => [255, 165, 0], 'navy' => [0, 0, 128], 'maroon' => [128, 0, 0],
        'purple' => [128, 0, 128], 'teal' => [0, 128, 128], 'lime' => [0, 255, 0], 'aqua' => [0, 255, 255],
        'fuchsia' => [255, 0, 255], 'olive' => [128, 128, 0],
    ];

    /**
     * @return array<int, array{code: string, message: string, count: int}>
     */
    public function check(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);

        $warnings = [];
        $add = function (string $code, string $message, int $count = 1) use (&$warnings): void {
            if ($count > 0) {
                $warnings[] = ['code' => $code, 'message' => $message, 'count' => $count];
            }
        };

        $root = $dom->getElementsByTagName('html')->item(0);
        $lang = $root instanceof DOMElement ? strtolower(trim($root->getAttribute('lang'))) : '';
        if ($lang === '' || $lang === 'und') {
            $add(self::MISSING_LANG, 'The <html> element has no language (lang attribute); screen readers cannot pick the right voice. Set lang on <mjml lang="...">.');
        }

        $title = $dom->getElementsByTagName('title')->item(0);
        if ($title === null || trim($title->textContent) === '') {
            $add(self::MISSING_TITLE, 'The e-mail has no <title>; add <mj-title> to the head of the template.');
        }

        $missingAlt = 0;
        foreach ($xpath->query('//img[not(@alt)]') as $img) {
            if (!$this->isTrackingPixel($img)) {
                $missingAlt++;
            }
        }
        $add(self::MISSING_ALT, 'Images without an alt attribute; describe the image, or use alt="" when it is purely decorative.', $missingAlt);

        $emptyLinks = 0;
        foreach ($xpath->query('//a[@href]') as $a) {
            if (!$this->linkHasName($a, $xpath)) {
                $emptyLinks++;
            }
        }
        $add(self::EMPTY_LINK, 'Links without text; screen readers announce them only as "link".', $emptyLinks);

        $layoutTables = 0;
        foreach ($xpath->query('//table') as $table) {
            $role = strtolower(trim($table->getAttribute('role')));
            if (!in_array($role, ['presentation', 'none'], true) && $xpath->query('.//th', $table)->length === 0) {
                $layoutTables++;
            }
        }
        $add(self::LAYOUT_TABLE, 'Tables used for layout without role="presentation"; screen readers announce them as data tables.', $layoutTables);

        $low = $this->lowContrast($xpath);
        $add(self::LOW_CONTRAST, 'Text colour contrast below the WCAG minimum (4.5:1, 3:1 for large text), e.g. ' . implode(', ', array_slice($low, 0, 3)) . '.', count($low));

        return $warnings;
    }

    private function isTrackingPixel(DOMElement $img): bool
    {
        $w = trim($img->getAttribute('width'));
        $h = trim($img->getAttribute('height'));

        return $w === '1' && $h === '1';
    }

    private function linkHasName(DOMElement $a, DOMXPath $xpath): bool
    {
        if (trim($a->getAttribute('aria-label')) !== '' || trim($a->getAttribute('title')) !== '') {
            return true;
        }
        if (trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $a->textContent)) !== '') {
            return true;
        }
        foreach ($xpath->query('.//img[@alt]', $a) as $img) {
            if (trim($img->getAttribute('alt')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[] "#fg on #bg" descriptions of the offending elements
     */
    private function lowContrast(DOMXPath $xpath): array
    {
        $found = [];
        foreach ($xpath->query('//body//*[@style]') as $el) {
            /** @var DOMElement $el */
            $style = $this->parseStyle($el->getAttribute('style'));
            if (!isset($style['color']) || in_array($el->nodeName, ['style', 'script', 'head', 'title'], true)) {
                continue;
            }
            if (!$this->hasDirectText($el)) {
                continue;
            }
            $fg = $this->parseColor($style['color']);
            if ($fg === null) {
                continue;
            }
            $bg = $this->backgroundOf($el);
            $ratio = $this->ratio($fg, $bg);
            if ($ratio < $this->threshold($el, $style)) {
                $found[sprintf('%s on %s', $this->hex($fg), $this->hex($bg))] = true;
            }
        }

        return array_keys($found);
    }

    private function hasDirectText(DOMElement $el): bool
    {
        foreach ($el->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE && trim($node->textContent) !== '') {
                return true;
            }
        }

        return false;
    }

    private function threshold(DOMElement $el, array $style): float
    {
        $size = null;
        $bold = false;
        for ($node = $el; $node instanceof DOMElement; $node = $node->parentNode) {
            $s = $this->parseStyle($node->getAttribute('style'));
            if ($size === null && isset($s['font-size']) && preg_match('/^([\d.]+)(px|pt)$/', $s['font-size'], $m)) {
                $size = $m[2] === 'pt' ? (float) $m[1] * 4 / 3 : (float) $m[1];
            }
            if (!$bold && isset($s['font-weight']) && ($s['font-weight'] === 'bold' || (int) $s['font-weight'] >= 700)) {
                $bold = true;
            }
        }

        return ($size !== null && ($size >= 24 || ($bold && $size >= 18.66))) ? 3.0 : 4.5;
    }

    /**
     * Nearest explicit background of the element or its ancestors; white when none is set.
     */
    private function backgroundOf(DOMElement $el): array
    {
        for ($node = $el; $node instanceof DOMElement; $node = $node->parentNode) {
            $style = $this->parseStyle($node->getAttribute('style'));
            foreach (['background-color', 'background'] as $prop) {
                if (isset($style[$prop])) {
                    foreach (preg_split('/\s+(?![^(]*\))/', $style[$prop]) as $part) {
                        $color = $this->parseColor($part);
                        if ($color !== null) {
                            return $color;
                        }
                    }
                }
            }
            if ($node->hasAttribute('bgcolor') && ($c = $this->parseColor($node->getAttribute('bgcolor'))) !== null) {
                return $c;
            }
        }

        return [255, 255, 255];
    }

    /**
     * @return array<string, string>
     */
    private function parseStyle(string $style): array
    {
        $result = [];
        foreach (explode(';', $style) as $declaration) {
            $pos = strpos($declaration, ':');
            if ($pos === false) {
                continue;
            }
            $result[strtolower(trim(substr($declaration, 0, $pos)))] = strtolower(trim(str_ireplace('!important', '', substr($declaration, $pos + 1))));
        }

        return $result;
    }

    /**
     * @return int[]|null [r, g, b]
     */
    private function parseColor(string $value): ?array
    {
        $value = strtolower(trim($value));
        if (preg_match('/^#([0-9a-f]{3})$/', $value, $m)) {
            return [hexdec($m[1][0] . $m[1][0]), hexdec($m[1][1] . $m[1][1]), hexdec($m[1][2] . $m[1][2])];
        }
        if (preg_match('/^#([0-9a-f]{6})$/', $value, $m)) {
            return [hexdec(substr($m[1], 0, 2)), hexdec(substr($m[1], 2, 2)), hexdec(substr($m[1], 4, 2))];
        }
        if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/', $value, $m)) {
            return [min(255, (int) $m[1]), min(255, (int) $m[2]), min(255, (int) $m[3])];
        }

        return self::NAMED_COLORS[$value] ?? null;
    }

    private function luminance(array $rgb): float
    {
        $c = array_map(function (int $v): float {
            $v /= 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    private function ratio(array $a, array $b): float
    {
        $la = $this->luminance($a);
        $lb = $this->luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private function hex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', ...$rgb);
    }
}
