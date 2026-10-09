<?php

namespace Ulams\LivingCourse\Connectors\Url;

use Dom\HTMLDocument;
use League\HTMLToMarkdown\HtmlConverter as LeagueConverter;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\LivingCourse\Connectors\ConnectorException;

/**
 * Web page to Markdown for the URL connector. The HTML is parsed with PHP 8.4's HTML5 parser (no
 * network, no entity loading), reduced to the element a CSS selector names (main content), cleaned
 * of scripts, navigation and forms, with links made absolute and images dropped, then converted by
 * league/html-to-markdown. Everything is data: nothing in the page is executed or followed.
 */
final class HtmlConverter
{
    public const DEFAULT_SELECTOR = 'main, article, [role=main], body';

    private const REMOVE = ['script', 'style', 'iframe', 'noscript', 'form', 'nav', 'footer', 'svg', 'img', 'picture', 'video', 'audio', 'canvas', 'object', 'embed', 'button', 'input', 'select', 'textarea', 'template'];

    /** @return array{markdown:string,title:?string} */
    public function convert(string $html, string $pageUrl, string $selector = self::DEFAULT_SELECTOR): array
    {
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $title = trim((string) $document->querySelector('title')?->textContent) ?: null;
        try {
            $root = $document->querySelector($selector !== '' ? $selector : self::DEFAULT_SELECTOR);
        } catch (\Throwable) {
            throw new ConnectorException('The CSS selector for the main content is not valid.');
        }
        $root ??= $document->body;
        if ($root === null) {
            throw new ConnectorException('The page has no content to read.');
        }
        foreach (self::REMOVE as $tag) {
            foreach (iterator_to_array($root->querySelectorAll($tag)) as $node) {
                $node->remove();
            }
        }
        foreach ($root->querySelectorAll('a[href]') as $link) {
            $absolute = self::absolute((string) $link->getAttribute('href'), $pageUrl);
            if ($absolute === null) {
                $link->removeAttribute('href');
            } else {
                $link->setAttribute('href', $absolute);
            }
        }
        $converter = new LeagueConverter(['strip_tags' => true, 'header_style' => 'atx', 'hard_break' => false, 'remove_nodes' => 'script style', 'strip_placeholder_links' => true, 'italic_style' => '*', 'bold_style' => '**']);
        $markdown = SourceIngestor::cleanMarkdown($converter->convert($root->innerHTML));
        $heading = trim((string) $root->querySelector('h1')?->textContent);
        if ($heading === '' && $title !== null && !preg_match('/^\s*#\s/m', $markdown)) {
            $markdown = '# ' . $title . "\n\n" . $markdown;
        }

        return ['markdown' => $markdown, 'title' => $heading !== '' ? $heading : $title];
    }

    /** Relative links become absolute; `javascript:`, `data:` and other schemes are dropped. */
    private static function absolute(string $href, string $base): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }
        if (preg_match('~^([a-z][a-z0-9+.-]*):~i', $href, $m)) {
            return in_array(strtolower($m[1]), ['http', 'https', 'mailto'], true) ? $href : null;
        }
        $b = parse_url($base);
        if (!isset($b['scheme'], $b['host'])) {
            return null;
        }
        $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($href, '//')) {
            return $b['scheme'] . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }
        $dir = rtrim(dirname(($b['path'] ?? '/') . 'x'), '/');

        return $origin . $dir . '/' . $href;
    }
}
