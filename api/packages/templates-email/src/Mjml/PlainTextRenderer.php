<?php

namespace Ulams\TemplatesEmail\Mjml;

use Qferrer\Mjml\RendererInterface;

/**
 * Used when no MJML renderer is configured: keeps the text of the template and drops the layout, so a
 * message is still delivered (with a readable body) instead of failing or sending an empty mail.
 */
class PlainTextRenderer implements RendererInterface
{
    public function render(string $content): string
    {
        // <mj-head>, <mj-style> and comments carry no readable text
        $content = (string) preg_replace('~<mj-head\b.*?</mj-head>|<mj-style\b.*?</mj-style>|<!--.*?-->~is', '', $content);
        // a link keeps its address, so "<mj-button href=...>Open</mj-button>" stays usable
        $content = (string) preg_replace_callback(
            '~<(mj-button|a)\b[^>]*\bhref=(["\'])(.*?)\2[^>]*>(.*?)</\1>~is',
            fn (array $m) => trim(strip_tags($m[4])) . ' (' . $m[3] . ')',
            $content
        );
        // block level MJML and HTML elements end a paragraph
        $content = (string) preg_replace('~</(mj-text|mj-button|mj-section|mj-column|mj-wrapper|mj-hero|p|div|h[1-6]|li|tr)>|<br\s*/?>~i', "\n\n", $content);

        $paragraphs = [];
        foreach (preg_split('~\n{2,}~', html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: [] as $paragraph) {
            $paragraph = trim((string) preg_replace('~\s+~u', ' ', $paragraph));
            if ($paragraph !== '') {
                $paragraphs[] = '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
            }
        }

        return implode("\n", $paragraphs);
    }
}
