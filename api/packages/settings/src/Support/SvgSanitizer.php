<?php

namespace Ulams\Settings\Support;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Checks and rewrites an uploaded SVG logo. An allow-list, not a block-list: only the drawing elements and
 * presentation attributes below survive, and anything else makes the upload fail with the name of the
 * offending element or attribute (a logo is drawn with shapes, it never needs a script, a style sheet, an
 * embedded page or a reference to another address). The stored file is the re-serialised document, never
 * the bytes that were uploaded, so a polyglot or an XML trick cannot ride along.
 *
 * Refused: a DOCTYPE or entities, processing instructions, `script`, `style`, `foreignObject`, `a`, `image`,
 * `iframe`, `animate*` and `set` (they can rewrite an attribute after the check), event attributes, `href`
 * values other than a `#fragment`, and `url()` values other than `url(#fragment)`.
 */
final class SvgSanitizer
{
    public const NAMESPACE = 'http://www.w3.org/2000/svg';

    private const MAX_ELEMENTS = 2000;

    private const MAX_DEPTH = 32;

    private const ELEMENTS = [
        'svg', 'g', 'defs', 'title', 'desc', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'use', 'symbol',
    ];

    private const ATTRIBUTES = [
        'id', 'class', 'viewbox', 'width', 'height', 'x', 'y', 'cx', 'cy', 'r', 'rx', 'ry', 'x1', 'y1', 'x2', 'y2', 'd', 'points',
        'fill', 'fill-opacity', 'fill-rule', 'clip-rule', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
        'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'opacity', 'transform',
        'offset', 'stop-color', 'stop-opacity', 'gradientunits', 'gradienttransform', 'spreadmethod', 'fx', 'fy',
        'clip-path', 'mask', 'clippathunits', 'maskunits', 'maskcontentunits', 'preserveaspectratio', 'version',
        'font-family', 'font-size', 'font-weight', 'font-style', 'text-anchor', 'dominant-baseline', 'letter-spacing',
        'dx', 'dy', 'role', 'aria-label', 'aria-hidden', 'aria-labelledby', 'focusable', 'display', 'visibility', 'style', 'vector-effect',
    ];

    /**
     * @return string the sanitised SVG
     *
     * @throws UnsafeSvg
     */
    public static function sanitize(string $svg): string
    {
        $svg = ltrim($svg, "\xEF\xBB\xBF \t\r\n");
        // A DOCTYPE is where entity attacks start, and no logo needs one.
        if (preg_match('/<!\s*(DOCTYPE|ENTITY|ELEMENT|ATTLIST)/i', $svg) === 1) {
            throw new UnsafeSvg(__('The SVG contains a DOCTYPE or entity declaration, which is not allowed.'));
        }
        if (preg_match('/<\?(?!xml\s)/i', $svg) === 1) {
            throw new UnsafeSvg(__('The SVG contains a processing instruction, which is not allowed.'));
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument();
            // LIBXML_NONET: nothing is fetched; no LIBXML_NOENT: entities are not substituted.
            $loaded = $dom->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $loaded ? $dom->documentElement : null;
        if (!$root instanceof DOMElement) {
            throw new UnsafeSvg(__('The file is not well-formed SVG (XML).'));
        }
        if ($dom->doctype !== null) {
            throw new UnsafeSvg(__('The SVG contains a DOCTYPE or entity declaration, which is not allowed.'));
        }
        if ($root->namespaceURI !== self::NAMESPACE || $root->localName !== 'svg') {
            throw new UnsafeSvg(__('The root element must be an <svg> in the SVG namespace.'));
        }

        $count = 0;
        self::walk($root, 0, $count, new DOMXPath($dom));

        return (string) $dom->saveXML($root);
    }

    private static function walk(DOMElement $element, int $depth, int &$count, DOMXPath $xpath): void
    {
        if ($depth > self::MAX_DEPTH || ++$count > self::MAX_ELEMENTS) {
            throw new UnsafeSvg(__('The SVG is too complex for a logo.'));
        }
        if ($element->namespaceURI !== self::NAMESPACE || !in_array(strtolower($element->localName), self::ELEMENTS, true)) {
            throw new UnsafeSvg(__('The element <:name> is not allowed in a logo.', ['name' => $element->nodeName]));
        }
        $name = strtolower($element->localName);

        // namespace declarations are not attributes of the DOM: the SVG, the xlink (href only) and the XML namespace are the only ones a logo needs
        foreach ($xpath->query('namespace::*', $element) as $declared) {
            if (!in_array($declared->nodeValue, [self::NAMESPACE, 'http://www.w3.org/1999/xlink', 'http://www.w3.org/XML/1998/namespace'], true)) {
                throw new UnsafeSvg(__('The namespace :uri is not allowed in a logo.', ['uri' => mb_substr((string) $declared->nodeValue, 0, 80)]));
            }
        }

        $attributes = [];
        foreach ($element->attributes as $attribute) {
            $attributes[] = $attribute;
        }
        foreach ($attributes as $attribute) {
            self::checkAttribute($element, $attribute);
        }

        $remove = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                self::walk($child, $depth + 1, $count, $xpath);
            } elseif ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                // text belongs to the elements that show or name something; elsewhere it is dropped
                if (!in_array($name, ['text', 'tspan', 'title', 'desc'], true) && trim($child->textContent) !== '') {
                    throw new UnsafeSvg(__('The SVG has text in <:name>, which is not allowed.', ['name' => $element->nodeName]));
                }
            } elseif ($child->nodeType === XML_COMMENT_NODE) {
                $remove[] = $child;
            } else {
                throw new UnsafeSvg(__('The SVG contains a node that is not allowed (type :type).', ['type' => $child->nodeType]));
            }
        }
        foreach ($remove as $node) {
            $element->removeChild($node);
        }
    }

    private static function checkAttribute(DOMElement $element, DOMAttr $attribute): void
    {
        $name = strtolower($attribute->nodeName);
        $value = $attribute->value;

        // namespace declarations: the SVG namespace and the (href-only) xlink one
        if ($name === 'xmlns' || str_starts_with($name, 'xmlns:')) {
            if ($value !== self::NAMESPACE && $value !== 'http://www.w3.org/1999/xlink') {
                throw new UnsafeSvg(__('The namespace :uri is not allowed in a logo.', ['uri' => mb_substr($value, 0, 80)]));
            }

            return;
        }
        if ($name === 'xml:space') {
            return;
        }
        if (str_starts_with($name, 'on')) {
            throw new UnsafeSvg(__('The event attribute :name is not allowed.', ['name' => $attribute->nodeName]));
        }
        if ($name === 'href' || $name === 'xlink:href') {
            // only `use` refers to something, and only inside the file
            if (strtolower($element->localName) !== 'use' || preg_match('/^#[A-Za-z_][\w.:-]*$/', $value) !== 1) {
                throw new UnsafeSvg(__('The reference :name="…" is not allowed: only a #fragment inside the file on <use>.', ['name' => $attribute->nodeName]));
            }

            return;
        }
        if (!in_array($name, self::ATTRIBUTES, true)) {
            throw new UnsafeSvg(__('The attribute :name on <:element> is not allowed in a logo.', ['name' => $attribute->nodeName, 'element' => $element->nodeName]));
        }
        self::checkValue($attribute->nodeName, $value);
    }

    private static function checkValue(string $name, string $value): void
    {
        $lower = strtolower(html_entity_decode($value, ENT_QUOTES | ENT_HTML5));
        if (preg_match('/javascript\s*:|vbscript\s*:|data\s*:|expression\s*\(|@import|<|\\\\/', $lower) === 1) {
            throw new UnsafeSvg(__('The value of :name is not allowed.', ['name' => $name]));
        }
        // every url() must point inside the file
        if (preg_match_all('/url\s*\(([^)]*)\)/', $lower, $matches) > 0) {
            foreach ($matches[1] as $target) {
                if (preg_match('/^\s*[\'"]?#[a-z_][\w.:-]*[\'"]?\s*$/', $target) !== 1) {
                    throw new UnsafeSvg(__('The value of :name refers to another address with url(), which is not allowed.', ['name' => $name]));
                }
            }
        }
    }

    /** Intrinsic size from `width`/`height` or the `viewBox`, as far as it can be read; null when unknown. */
    public static function size(string $sanitized): ?array
    {
        $dom = new DOMDocument();
        if (!@$dom->loadXML($sanitized, LIBXML_NONET) || !$dom->documentElement instanceof DOMElement) {
            return null;
        }
        $root = $dom->documentElement;
        if (preg_match('/^\s*(-?[\d.]+)[\s,]+(-?[\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)\s*$/', $root->getAttribute('viewBox'), $m) === 1 && $m[3] > 0 && $m[4] > 0) {
            return [(float) $m[3], (float) $m[4]];
        }
        $w = (float) $root->getAttribute('width');
        $h = (float) $root->getAttribute('height');

        return $w > 0 && $h > 0 ? [$w, $h] : null;
    }
}
