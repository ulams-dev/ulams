<?php

namespace Ulams\CourseBuilder\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\CourseBuilder\ContentTypes\LiaScriptRenderer;

class LiaScriptRendererTest extends TestCase
{
    private function lesson(): array
    {
        return [
            'title' => 'Grind size',
            'summary' => 'How grind size changes the cup.',
            'blocks' => [
                ['kind' => 'paragraph', 'markdown' => 'Finer grinds extract **faster**.', 'citations' => ['frg_aaaaaaaaaaa2']],
                ['kind' => 'callout', 'markdown' => "Key takeaway:\nMatch the grind to the brew time.", 'citations' => ['frg_aaaaaaaaaaa2', 'frg_aaaaaaaaaaa3']],
            ],
            'selfChecks' => [
                ['type' => 'single', 'stem' => 'Which grind suits an espresso?', 'options' => [
                    ['text' => 'Coarse', 'correct' => false], ['text' => 'Fine', 'correct' => true], ['text' => 'Medium', 'correct' => false],
                ], 'explanation' => 'Espresso is short, so it needs a fine grind.', 'citations' => ['frg_aaaaaaaaaaa2']],
                ['type' => 'multiple', 'stem' => 'Which factors change extraction?', 'options' => [
                    ['text' => 'Grind size', 'correct' => true], ['text' => 'Water temperature', 'correct' => true], ['text' => 'Cup colour', 'correct' => false],
                ], 'explanation' => 'Colour does not affect extraction.', 'citations' => ['frg_aaaaaaaaaaa3']],
                ['type' => 'short', 'stem' => 'The usual ratio of coffee to water in a pour over is 1:?', 'options' => [
                    ['text' => '16', 'correct' => true], ['text' => 'sixteen', 'correct' => true],
                ], 'explanation' => 'Most guides use 1:16.', 'citations' => ['frg_aaaaaaaaaaa3']],
            ],
        ];
    }

    public function testARenderedLessonMatchesTheGoldenFile(): void
    {
        $text = LiaScriptRenderer::render($this->lesson(), ['frg_aaaaaaaaaaa2' => '§2 Grinding', 'frg_aaaaaaaaaaa3' => '§3 Ratios'], 'Coffee handbook', 'en');
        $golden = __DIR__ . '/golden/liascript-lesson.md';
        if (!is_file($golden)) {
            file_put_contents($golden, $text);
        }
        $this->assertSame(file_get_contents($golden), $text);
    }

    public function testQuizSyntax(): void
    {
        $single = LiaScriptRenderer::question($this->lesson()['selfChecks'][0]);
        $this->assertStringContainsString("- [( )] Coarse\n- [(X)] Fine\n- [( )] Medium\n***\nEspresso is short", $single);
        $this->assertStringContainsString("- [[X]] Grind size\n- [[X]] Water temperature\n- [[ ]] Cup colour", LiaScriptRenderer::question($this->lesson()['selfChecks'][1]));
        $this->assertStringContainsString('[[16|sixteen]]', LiaScriptRenderer::question($this->lesson()['selfChecks'][2]));
    }

    public function testCodeExecutionAndImportsAreRejectedAndStripped(): void
    {
        foreach (["```js\nlet a = 1\n```\n@eval", "x @input y", "import: https://example.com/macros.md", "link: https://example.com/x.css", "<script>alert(1)</script>", '[a](javascript:alert(1))', '@JS.eval'] as $bad) {
            $this->assertTrue(LiaScriptRenderer::unsafe($bad), $bad);
        }
        $this->assertFalse(LiaScriptRenderer::unsafe("A paragraph about the email at a@b.com and **bold**.\n\n```python\nprint('hi')\n```"));

        $lesson = ['title' => 'T', 'blocks' => [['kind' => 'paragraph', 'markdown' => "Text\nimport: https://example.com/m.md\nrun it @eval and (@input)", 'citations' => []]]];
        $text = LiaScriptRenderer::render($lesson, []);
        $this->assertStringNotContainsString('import:', $text);
        $this->assertStringNotContainsString('@eval', $text);
        $this->assertStringNotContainsString('@input', $text);
    }
}
