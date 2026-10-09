<?php

namespace Ulams\CourseBuilder\Ingestion;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Ulams\Uploads\Zip\ZipInspector;
use Ulams\Uploads\Zip\ZipLimits;
use ZipArchive;

/**
 * First-party DOCX → Markdown converter (no phpoffice/phpword: LGPL and large). Handles headings
 * (Heading N / Title styles and outline levels), paragraphs, bulleted and numbered lists, tables,
 * code (monospace runs or code styles) and hyperlinks. The archive is checked by the uploads
 * package's zip inspector first (zip-slip, symlinks, entry count, uncompressed size, ratio), and
 * XML is parsed without network access or entity expansion.
 */
final class DocxConverter
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const MONO = ['courier', 'consolas', 'menlo', 'monaco', 'mono', 'source code'];

    public function __construct(private readonly ZipInspector $inspector = new ZipInspector())
    {
    }

    /** @return array{markdown:string,title:?string} */
    public function convert(string $path, int $maxUncompressed): array
    {
        $this->inspector->inspect($path, new ZipLimits(maxEntries: 2000, maxUncompressed: $maxUncompressed, maxEntrySize: $maxUncompressed, maxRatio: 200));

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The DOCX file is not a readable archive.');
        }
        try {
            $document = $zip->getFromName('word/document.xml');
            if ($document === false) {
                throw new RuntimeException('The DOCX file has no word/document.xml.');
            }
            $styles = $this->styles((string) $zip->getFromName('word/styles.xml'));
            $links = $this->relationships((string) $zip->getFromName('word/_rels/document.xml.rels'));
            $title = $this->coreTitle((string) $zip->getFromName('docProps/core.xml'));
        } finally {
            $zip->close();
        }

        $dom = self::load($document);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::W);
        $xpath->registerNamespace('r', self::R);
        $body = $xpath->query('/w:document/w:body')->item(0);
        if (!$body instanceof DOMElement) {
            throw new RuntimeException('The DOCX document has no body.');
        }

        $out = [];
        $code = [];
        foreach ($body->childNodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            if ($node->localName === 'tbl') {
                $this->flushCode($out, $code);
                $out[] = $this->table($xpath, $node, $links);
                continue;
            }
            if ($node->localName !== 'p') {
                continue;
            }
            [$text, $allMono] = $this->runs($xpath, $node, $links);
            $style = $this->paragraphStyle($xpath, $node, $styles);
            $isCode = $allMono && trim($text) !== '' || str_contains(strtolower($style['name']), 'code');
            if ($isCode) {
                $code[] = $this->plainRuns($xpath, $node);
                continue;
            }
            $this->flushCode($out, $code);
            if (trim($text) === '') {
                continue;
            }
            if ($style['heading'] !== null) {
                $out[] = str_repeat('#', min(6, $style['heading'])) . ' ' . trim(strip_tags($text));
                continue;
            }
            if ($style['list'] !== null) {
                $indent = str_repeat('  ', $style['list']);
                $out[] = $indent . '- ' . trim($text);
                continue;
            }
            $out[] = trim($text);
        }
        $this->flushCode($out, $code);

        // join list items without blank lines between them
        $markdown = '';
        foreach ($out as $i => $block) {
            $isItem = (bool) preg_match('/^\s*- /', $block);
            $prevItem = $i > 0 && (bool) preg_match('/^\s*- /', $out[$i - 1]);
            $markdown .= ($i === 0 ? '' : ($isItem && $prevItem ? "\n" : "\n\n")) . $block;
        }

        return ['markdown' => trim($markdown) . "\n", 'title' => $title];
    }

    public static function load(string $xml): DOMDocument
    {
        if (preg_match('/<!DOCTYPE/i', $xml)) {
            throw new RuntimeException('DOCX parts with a DOCTYPE are not accepted.');
        }
        $dom = new DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOBLANKS)) {
            throw new RuntimeException('The DOCX document XML is invalid.');
        }

        return $dom;
    }

    /** @return array{0:string,1:bool} markdown text and whether every run is monospace */
    private function runs(DOMXPath $xpath, DOMElement $p, array $links): array
    {
        $text = '';
        $runs = 0;
        $mono = 0;
        foreach ($xpath->query('./w:r|./w:hyperlink', $p) as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            if ($node->localName === 'hyperlink') {
                $label = '';
                foreach ($xpath->query('.//w:t', $node) as $t) {
                    $label .= $t->textContent;
                }
                $rid = $node->getAttributeNS(self::R, 'id');
                $href = $links[$rid] ?? null;
                $text .= $href !== null && preg_match('#^(https?://|mailto:)#i', $href) ? '[' . $label . '](' . $href . ')' : $label;
                $runs++;
                continue;
            }
            $value = '';
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    if ($child->localName === 't') {
                        $value .= $child->textContent;
                    } elseif ($child->localName === 'tab') {
                        $value .= "\t";
                    } elseif ($child->localName === 'br') {
                        $value .= "\n";
                    }
                }
            }
            if ($value === '') {
                continue;
            }
            $runs++;
            $isMono = $this->isMono($xpath, $node);
            $bold = $xpath->query('./w:rPr/w:b[not(@w:val="0") and not(@w:val="false")]', $node)->length > 0;
            $italic = $xpath->query('./w:rPr/w:i[not(@w:val="0") and not(@w:val="false")]', $node)->length > 0;
            if ($isMono) {
                $mono++;
                $value = '`' . str_replace('`', '\\`', $value) . '`';
            } elseif ($bold && trim($value) !== '') {
                $value = '**' . $value . '**';
            } elseif ($italic && trim($value) !== '') {
                $value = '*' . $value . '*';
            }
            $text .= $value;
        }
        $text = str_replace('``', '', $text);

        return [$text, $runs > 0 && $mono === $runs];
    }

    private function plainRuns(DOMXPath $xpath, DOMElement $p): string
    {
        $text = '';
        foreach ($xpath->query('.//w:t|.//w:tab|.//w:br', $p) as $node) {
            $text .= match ($node->localName) {
                'tab' => "\t",
                'br' => "\n",
                default => $node->textContent,
            };
        }

        return $text;
    }

    private function isMono(DOMXPath $xpath, DOMElement $run): bool
    {
        foreach ($xpath->query('./w:rPr/w:rFonts', $run) as $fonts) {
            if ($fonts instanceof DOMElement) {
                $font = strtolower($fonts->getAttributeNS(self::W, 'ascii') . ' ' . $fonts->getAttributeNS(self::W, 'hAnsi'));
                foreach (self::MONO as $needle) {
                    if (str_contains($font, $needle)) {
                        return true;
                    }
                }
            }
        }
        $style = $xpath->query('./w:rPr/w:rStyle', $run)->item(0);

        return $style instanceof DOMElement && str_contains(strtolower($style->getAttributeNS(self::W, 'val')), 'code');
    }

    /** @return array{heading:?int,list:?int,name:string} */
    private function paragraphStyle(DOMXPath $xpath, DOMElement $p, array $styles): array
    {
        $styleId = '';
        $node = $xpath->query('./w:pPr/w:pStyle', $p)->item(0);
        if ($node instanceof DOMElement) {
            $styleId = $node->getAttributeNS(self::W, 'val');
        }
        $name = strtolower($styles[$styleId]['name'] ?? $styleId);
        $heading = null;
        if (preg_match('/^heading\s*(\d)$/', $name, $m)) {
            $heading = (int) $m[1];
        } elseif ($name === 'title') {
            $heading = 1;
        } else {
            $outline = $xpath->query('./w:pPr/w:outlineLvl', $p)->item(0);
            if ($outline instanceof DOMElement) {
                $heading = (int) $outline->getAttributeNS(self::W, 'val') + 1;
            } elseif (isset($styles[$styleId]['outline'])) {
                $heading = $styles[$styleId]['outline'] + 1;
            }
        }
        $list = null;
        $ilvl = $xpath->query('./w:pPr/w:numPr/w:ilvl', $p)->item(0);
        if ($xpath->query('./w:pPr/w:numPr', $p)->length > 0 || str_contains($name, 'list')) {
            $list = $ilvl instanceof DOMElement ? (int) $ilvl->getAttributeNS(self::W, 'val') : 0;
        }

        return ['heading' => $heading, 'list' => $heading === null ? $list : null, 'name' => $name];
    }

    private function table(DOMXPath $xpath, DOMElement $tbl, array $links): string
    {
        $rows = [];
        foreach ($xpath->query('./w:tr', $tbl) as $tr) {
            $cells = [];
            foreach ($xpath->query('./w:tc', $tr) as $tc) {
                $parts = [];
                foreach ($xpath->query('./w:p', $tc) as $p) {
                    $parts[] = trim($this->runs($xpath, $p, $links)[0]);
                }
                $cells[] = str_replace('|', '\\|', trim(implode(' ', array_filter($parts))));
            }
            $rows[] = $cells;
        }
        if ($rows === []) {
            return '';
        }
        $width = max(array_map('count', $rows));
        $lines = [];
        foreach ($rows as $i => $cells) {
            $cells = array_pad($cells, $width, '');
            $lines[] = '| ' . implode(' | ', $cells) . ' |';
            if ($i === 0) {
                $lines[] = '|' . str_repeat(' --- |', $width);
            }
        }

        return implode("\n", $lines);
    }

    private function flushCode(array &$out, array &$code): void
    {
        if ($code !== []) {
            $out[] = "```\n" . rtrim(implode("\n", $code)) . "\n```";
            $code = [];
        }
    }

    /** @return array<string,array{name:string,outline?:int}> */
    private function styles(string $xml): array
    {
        if ($xml === '') {
            return [];
        }
        $dom = self::load($xml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::W);
        $styles = [];
        foreach ($xpath->query('//w:style') as $style) {
            if (!$style instanceof DOMElement) {
                continue;
            }
            $id = $style->getAttributeNS(self::W, 'styleId');
            $nameNode = $xpath->query('./w:name', $style)->item(0);
            $entry = ['name' => $nameNode instanceof DOMElement ? $nameNode->getAttributeNS(self::W, 'val') : $id];
            $outline = $xpath->query('./w:pPr/w:outlineLvl', $style)->item(0);
            if ($outline instanceof DOMElement) {
                $entry['outline'] = (int) $outline->getAttributeNS(self::W, 'val');
            }
            $styles[$id] = $entry;
        }

        return $styles;
    }

    /** @return array<string,string> relationship id → external target */
    private function relationships(string $xml): array
    {
        if ($xml === '') {
            return [];
        }
        $dom = self::load($xml);
        $links = [];
        foreach ($dom->getElementsByTagName('Relationship') as $rel) {
            if ($rel instanceof DOMElement && str_ends_with($rel->getAttribute('Type'), '/hyperlink')) {
                $links[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
            }
        }

        return $links;
    }

    private function coreTitle(string $xml): ?string
    {
        if ($xml === '') {
            return null;
        }
        $title = trim((string) (self::load($xml)->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'title')->item(0)?->textContent));

        return $title !== '' ? $title : null;
    }
}
