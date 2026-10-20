<?php

namespace Ulams\LivingCourse\Connectors\Notion;

/**
 * Notion blocks to Markdown. Pure: the API answer in, text out. Only the text of a page is kept; images,
 * files and embeds are dropped (a caption stays), links must be http(s), and nothing is interpreted.
 */
final class BlocksToMarkdown
{
    /**
     * @param array<int,array<string,mixed>> $blocks blocks with their children under `children`
     */
    public static function render(array $blocks, int $depth = 0): string
    {
        $out = [];
        $number = 0;
        foreach ($blocks as $block) {
            $type = (string) ($block['type'] ?? '');
            $number = $type === 'numbered_list_item' ? $number + 1 : 0;
            $text = self::richText((array) ($block[$type]['rich_text'] ?? []));
            $children = (array) ($block['children'] ?? []);
            $indent = str_repeat('  ', $depth);
            $nested = $children === [] ? '' : "\n" . self::render($children, $depth + 1);
            $line = match ($type) {
                'paragraph' => $text,
                'heading_1' => $text === '' ? '' : "# {$text}",
                'heading_2' => $text === '' ? '' : "## {$text}",
                'heading_3' => $text === '' ? '' : "### {$text}",
                'bulleted_list_item' => "{$indent}- {$text}" . $nested,
                'numbered_list_item' => "{$indent}{$number}. {$text}" . $nested,
                'to_do' => "{$indent}- [" . (!empty($block['to_do']['checked']) ? 'x' : ' ') . "] {$text}" . $nested,
                'toggle' => "**{$text}**" . ($children === [] ? '' : "\n\n" . self::render($children, 0)),
                'quote' => self::prefix($text . ($children === [] ? '' : "\n\n" . self::render($children, 0)), '> '),
                'callout' => self::prefix(trim(($block['callout']['icon']['emoji'] ?? '') . ' ' . $text) . ($children === [] ? '' : "\n\n" . self::render($children, 0)), '> '),
                'code' => "```" . preg_replace('/[^a-z0-9+#-]/i', '', (string) ($block['code']['language'] ?? '')) . "\n" . self::plain((array) ($block['code']['rich_text'] ?? [])) . "\n```",
                'divider' => '---',
                'equation' => '`' . trim((string) ($block['equation']['expression'] ?? '')) . '`',
                'table' => self::table($children),
                'column_list', 'column', 'synced_block', 'template' => self::render($children, 0),
                'image', 'file', 'pdf', 'video', 'audio' => self::caption($block, $type),
                'bookmark', 'embed', 'link_preview' => self::link($block, $type),
                'child_page' => '',
                default => $text,
            };
            if (trim($line) !== '') {
                $out[] = $line;
            }
        }

        // list items stay together; everything else is a paragraph apart
        $joined = '';
        $previousList = false;
        foreach ($out as $line) {
            $list = (bool) preg_match('/^\s*(- |\d+\. )/', $line);
            $joined .= $joined === '' ? $line : (($list && $previousList) ? "\n" : "\n\n") . $line;
            $previousList = $list;
        }

        return $joined;
    }

    /** @param array<int,array<string,mixed>> $items */
    public static function richText(array $items): string
    {
        $out = '';
        foreach ($items as $item) {
            $text = (string) ($item['plain_text'] ?? '');
            if ($text === '') {
                continue;
            }
            $a = (array) ($item['annotations'] ?? []);
            if (!empty($a['code'])) {
                $text = '`' . str_replace('`', "'", $text) . '`';
            } elseif (trim($text) !== '') {
                $lead = (string) (preg_match('/^\s+/', $text, $m) ? $m[0] : '');
                $trail = (string) (preg_match('/\s+$/', $text, $m) ? $m[0] : '');
                $core = trim($text);
                foreach (['bold' => '**', 'italic' => '*', 'strikethrough' => '~~'] as $key => $mark) {
                    if (!empty($a[$key])) {
                        $core = $mark . $core . $mark;
                    }
                }
                $text = $lead . $core . $trail;
            }
            $href = (string) ($item['href'] ?? ($item['text']['link']['url'] ?? ''));
            if ($href !== '' && preg_match('~^https?://~i', $href) && trim($text) !== '') {
                $text = '[' . trim($text) . '](' . str_replace([' ', ')'], ['%20', '%29'], $href) . ')';
            }
            $out .= $text;
        }

        return trim($out);
    }

    /** @param array<int,array<string,mixed>> $items */
    private static function plain(array $items): string
    {
        return rtrim(implode('', array_map(fn ($i) => (string) ($i['plain_text'] ?? ''), $items)));
    }

    private static function prefix(string $text, string $prefix): string
    {
        return implode("\n", array_map(fn ($l) => rtrim($prefix . $l), explode("\n", trim($text))));
    }

    /** @param array<int,array<string,mixed>> $rows */
    private static function table(array $rows): string
    {
        $lines = [];
        foreach ($rows as $n => $row) {
            $cells = array_map(fn ($cell) => str_replace(['|', "\n"], ['\\|', ' '], self::richText((array) $cell)), (array) ($row['table_row']['cells'] ?? []));
            if ($cells === []) {
                continue;
            }
            $lines[] = '| ' . implode(' | ', $cells) . ' |';
            if ($n === 0) {
                $lines[] = '|' . str_repeat(' --- |', count($cells));
            }
        }

        return implode("\n", $lines);
    }

    /** @param array<string,mixed> $block */
    private static function caption(array $block, string $type): string
    {
        $caption = self::richText((array) ($block[$type]['caption'] ?? []));

        return $caption === '' ? '' : "*{$caption}*";
    }

    /** @param array<string,mixed> $block */
    private static function link(array $block, string $type): string
    {
        $url = (string) ($block[$type]['url'] ?? '');
        $caption = self::richText((array) ($block[$type]['caption'] ?? []));
        if (!preg_match('~^https?://~i', $url)) {
            return $caption;
        }

        return '[' . ($caption !== '' ? $caption : $url) . '](' . str_replace([' ', ')'], ['%20', '%29'], $url) . ')';
    }
}
