<?php

namespace Ulams\CourseBuilder\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\CourseBuilder\Apply\GiftRenderer;
use Ulams\CourseBuilder\Apply\LessonMarkdown;
use Ulams\CourseBuilder\Blueprint\BlueprintDiff;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Ingestion\FragmentId;

class RenderingTest extends TestCase
{
    private function q(string $type, array $options, string $stem = 'Pick one: a = b?'): array
    {
        return ['type' => $type, 'stem' => $stem, 'options' => array_map(fn ($o) => ['id' => 'x', 'text' => $o[0], 'correct' => $o[1]], $options), 'explanation' => 'Because {it} is.'];
    }

    public function testGiftSingleChoiceEscapesControlCharacters(): void
    {
        $gift = GiftRenderer::render($this->q('single', [['1:16', true], ['1:2', false], ['~no', false]]));
        $this->assertSame('Pick one\: a \= b? {=1\:16#Because \{it\} is. ~1\:2 ~\~no}', $gift);
    }

    public function testGiftMultipleTrueFalseAndShort(): void
    {
        $this->assertSame('Pick one\: a \= b? {~%50%A ~%50%B ~%-100%C}', GiftRenderer::render($this->q('multiple', [['A', true], ['B', true], ['C', false]])));
        $this->assertSame('Pick one\: a \= b? {F}', GiftRenderer::render($this->q('truefalse', [['True', false], ['False', true]])));
        $this->assertSame('Pick one\: a \= b? {=four =4}', GiftRenderer::render($this->q('short', [['four', true], ['4', true]])));
        $this->assertSame('Q {~%33.33333%A ~%33.33333%B ~%33.33333%C ~%-100%D}', GiftRenderer::render($this->q('multiple', [['A', true], ['B', true], ['C', true], ['D', false]], 'Q')));
    }

    public function testLessonMarkdownHasSourcesAndNoHtml(): void
    {
        $md = LessonMarkdown::render(['blocks' => [
            ['kind' => 'paragraph', 'markdown' => 'Hello <b>world</b> [x](javascript:alert(1)) ![i](https://evil.example/a.png)', 'citations' => ['frg_aaaaaaaaaaaa']],
            ['kind' => 'callout', 'markdown' => "Key\nline", 'citations' => ['frg_bbbbbbbbbbbb']],
            ['kind' => 'code', 'markdown' => "```html\n<b>kept in code</b>\n```", 'citations' => ['frg_aaaaaaaaaaaa']],
        ]], ['frg_aaaaaaaaaaaa' => '§1 Intro'], 'Handbook');

        $this->assertStringNotContainsString('<b>world</b>', $md);
        $this->assertStringNotContainsString('javascript:', $md);
        $this->assertStringNotContainsString('evil.example', $md);
        $this->assertStringContainsString("> Key\n> line", $md);
        $this->assertStringContainsString('<b>kept in code</b>', $md);
        $this->assertStringContainsString("**Sources**\n\n1. §1 Intro — Handbook\n2. frg_bbbbbbbbbbbb — Handbook", $md);
    }

    public function testMarkupCheck(): void
    {
        $this->assertNotSame([], Checks::markup('Text <div>x</div>', 'w'));
        $this->assertNotSame([], Checks::markup('<img src=x onerror=alert(1)>', 'w'));
        $this->assertSame([], Checks::markup("Use `<div>` or\n```\n<p>\n```\nand 3 < 4 > 2", 'w'));
    }

    public function testQuestionShapesAndSupport(): void
    {
        $this->assertSame([], Checks::questionShape($this->q('single', [['a', true], ['b', false], ['c', false]]), 'q'));
        $this->assertNotSame([], Checks::questionShape($this->q('single', [['a', true], ['b', true], ['c', false]]), 'q'));
        $this->assertNotSame([], Checks::questionShape($this->q('truefalse', [['Yes', true], ['No', false]]), 'q'));
        $question = ['stem' => 'How much water for 20 g of coffee at 1:16?', 'options' => [['text' => '320 g', 'correct' => true]]];
        $this->assertSame([], Checks::quizSupport($question, ['For 20 g of coffee at 1:16 you need 320 g of water.'], 'q'));
        $this->assertNotSame([], Checks::quizSupport($question, ['Tea leaves unfurl in hot water.'], 'q'));
    }

    public function testDiffMatchesElementsById(): void
    {
        $old = ['course' => ['id' => 'c', 'title' => 'T', 'objectives' => []], 'modules' => [
            ['id' => 'm1', 'title' => 'One', 'lessons' => [['id' => 'l1', 'title' => 'L', 'minutes' => 5, 'objectives' => [], 'blocks' => [['id' => 'b1', 'kind' => 'paragraph', 'markdown' => 'old', 'citations' => []]], 'quiz' => null]]],
            ['id' => 'm2', 'title' => 'Two', 'lessons' => []],
        ], 'finalTest' => null];
        $new = $old;
        $new['modules'] = [$old['modules'][1], $old['modules'][0]]; // reordered only
        $new['modules'][1]['lessons'][0]['blocks'][0]['markdown'] = 'new';
        $new['modules'][] = ['id' => 'm3', 'title' => 'Three', 'lessons' => []];
        unset($new['modules'][0]);
        $new['modules'] = array_values($new['modules']);

        $changes = collect(BlueprintDiff::compare($old, $new))->keyBy('id');
        $this->assertSame('changed', $changes['b1']['kind']);
        $this->assertSame([['field' => 'markdown', 'label' => 'Content', 'before' => 'old', 'after' => 'new']], $changes['b1']['fields']);
        $this->assertSame('added', $changes['m3']['kind']);
        $this->assertSame('removed', $changes['m2']['kind']);
        $this->assertSame(['b1'], array_column(BlueprintDiff::forElement(array_values($changes->all()), 'l1'), 'id'));
    }

    public function testFragmentIdFormat(): void
    {
        $id = FragmentId::make('source', ['A', 'B'], 0);
        $this->assertMatchesRegularExpression('/^frg_[a-z2-7]{12}$/', $id);
        $this->assertSame($id, FragmentId::make('source', ['A', 'B'], 0));
        $this->assertNotSame($id, FragmentId::make('other', ['A', 'B'], 0));
    }
}
