<?php

namespace Ulams\LivingCourse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\LivingCourse\Connectors\Notion\BlocksToMarkdown;

class BlocksToMarkdownTest extends TestCase
{
    private function rich(string $text, array $annotations = [], ?string $href = null): array
    {
        return ['plain_text' => $text, 'annotations' => $annotations, 'href' => $href];
    }

    private function block(string $type, array $texts, array $extra = [], array $children = []): array
    {
        return ['type' => $type, $type => ['rich_text' => $texts] + $extra, 'children' => $children];
    }

    public function testHeadingsParagraphsListsAndCodeBecomeMarkdown(): void
    {
        $md = BlocksToMarkdown::render([
            $this->block('heading_1', [$this->rich('Brewing')]),
            $this->block('paragraph', [$this->rich('Use '), $this->rich('fresh', ['bold' => true]), $this->rich(' beans, '), $this->rich('ground', ['italic' => true]), $this->rich(' just before.')]),
            $this->block('bulleted_list_item', [$this->rich('Grind')], [], [$this->block('bulleted_list_item', [$this->rich('Medium')])]),
            $this->block('bulleted_list_item', [$this->rich('Pour')]),
            $this->block('numbered_list_item', [$this->rich('First')]),
            $this->block('numbered_list_item', [$this->rich('Second')]),
            $this->block('to_do', [$this->rich('Buy beans')], ['checked' => true]),
            $this->block('code', [$this->rich("echo 1;\necho 2;")], ['language' => 'php']),
            $this->block('quote', [$this->rich('Patience')]),
            $this->block('divider', []),
        ]);

        $this->assertStringContainsString("# Brewing\n\nUse **fresh** beans, *ground* just before.", $md);
        $this->assertStringContainsString("- Grind\n  - Medium\n- Pour", $md);
        $this->assertStringContainsString("1. First\n2. Second", $md);
        $this->assertStringContainsString('- [x] Buy beans', $md);
        $this->assertStringContainsString("```php\necho 1;\necho 2;\n```", $md);
        $this->assertStringContainsString('> Patience', $md);
        $this->assertStringContainsString('---', $md);
    }

    public function testOnlyHttpLinksSurviveAndMediaAreDropped(): void
    {
        $md = BlocksToMarkdown::render([
            $this->block('paragraph', [$this->rich('safe', [], 'https://example.org/a b'), $this->rich(' and '), $this->rich('evil', [], 'javascript:alert(1)')]),
            ['type' => 'image', 'image' => ['type' => 'external', 'external' => ['url' => 'https://evil.example/x.png'], 'caption' => [$this->rich('A cup')]], 'children' => []],
            ['type' => 'embed', 'embed' => ['url' => 'javascript:alert(1)', 'caption' => []], 'children' => []],
            ['type' => 'bookmark', 'bookmark' => ['url' => 'https://example.org/read', 'caption' => []], 'children' => []],
            ['type' => 'child_page', 'child_page' => ['title' => 'Nested'], 'children' => []],
        ]);

        $this->assertStringContainsString('[safe](https://example.org/a%20b)', $md);
        $this->assertStringNotContainsString('javascript', $md);
        $this->assertStringNotContainsString('evil.example', $md);
        $this->assertStringContainsString('*A cup*', $md);
        $this->assertStringContainsString('[https://example.org/read](https://example.org/read)', $md);
        $this->assertStringNotContainsString('Nested', $md);
    }

    public function testTablesCalloutsAndToggles(): void
    {
        $row = fn (array $cells) => ['type' => 'table_row', 'table_row' => ['cells' => array_map(fn ($c) => [$this->rich($c)], $cells)]];
        $md = BlocksToMarkdown::render([
            ['type' => 'table', 'table' => ['has_column_header' => true], 'children' => [$row(['Ratio', 'Water']), $row(['1:15', '300 g | approx'])]],
            $this->block('callout', [$this->rich('Mind the temperature')], ['icon' => ['emoji' => '!']]),
            $this->block('toggle', [$this->rich('More')], [], [$this->block('paragraph', [$this->rich('Hidden detail.')])]),
        ]);

        $this->assertStringContainsString("| Ratio | Water |\n| --- | --- |\n| 1:15 | 300 g \\| approx |", $md);
        $this->assertStringContainsString('> ! Mind the temperature', $md);
        $this->assertStringContainsString("**More**\n\nHidden detail.", $md);
    }
}
