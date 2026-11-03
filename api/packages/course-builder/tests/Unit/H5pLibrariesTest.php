<?php

namespace Ulams\CourseBuilder\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\CourseBuilder\ContentTypes\H5pLibraries;

class H5pLibrariesTest extends TestCase
{
    public function testBlanksMapToTheAsteriskSyntax(): void
    {
        $params = H5pLibraries::params('H5P.Blanks', ['instruction' => 'Fill in <the> blanks.', 'items' => [
            ['text' => 'Espresso needs a {1} grind and about {2} bar.', 'blanks' => [
                ['answer' => 'fine', 'alternatives' => ['finer'], 'tip' => 'Not coarse'],
                ['answer' => '9', 'alternatives' => [], 'tip' => ''],
            ]],
        ]]);
        $this->assertSame('<p>Fill in &lt;the&gt; blanks.</p>', $params['text']);
        $this->assertSame(['<p>Espresso needs a *fine/finer:Not coarse* grind and about *9* bar.</p>'], $params['questions']);
        $this->assertTrue($params['behaviour']['enableRetry']);
    }

    public function testDragTextAndDialogcards(): void
    {
        $drag = H5pLibraries::params('H5P.DragText', ['instruction' => 'Drag.', 'text' => 'A {1} grind and a {2} brew.', 'words' => [['answer' => 'fine', 'tip' => ''], ['answer' => 'short', 'tip' => 'time']], 'distractors' => ['coarse', '']]);
        $this->assertSame('A *fine* grind and a *short:time* brew.', $drag['textField']);
        $this->assertSame('*coarse*', $drag['distractors']);
        $cards = H5pLibraries::params('H5P.Dialogcards', ['title' => 'Terms', 'instruction' => 'Turn them.', 'cards' => [
            ['front' => 'Crema', 'back' => 'The foam on an espresso', 'tip' => 'Look at the top'], ['front' => 'Dose', 'back' => 'The weight of ground coffee', 'tip' => ''],
        ]]);
        $this->assertSame('Crema', $cards['dialogs'][0]['text']);
        $this->assertSame('<p>Look at the top</p>', $cards['dialogs'][0]['tips']['front']['tip']);
        $this->assertArrayNotHasKey('tips', $cards['dialogs'][1]);
    }

    public function testStructureAndMarkupAreChecked(): void
    {
        $bad = ['instruction' => 'Go', 'items' => [['text' => 'One {1} and {3}', 'blanks' => [['answer' => 'a/b', 'alternatives' => [], 'tip' => ''], ['answer' => 'c', 'alternatives' => [], 'tip' => '']]]]];
        $errors = implode("\n", H5pLibraries::errors('H5P.Blanks', $bad, 'x'));
        $this->assertStringContainsString('placeholders', $errors);
        $this->assertStringContainsString('cannot be empty or contain', $errors);
        $this->assertStringContainsString('contains HTML', implode("\n", H5pLibraries::errors('H5P.Dialogcards', ['title' => 'T', 'instruction' => 'i', 'cards' => [['front' => '<b>x</b>', 'back' => 'y', 'tip' => '']]], 'x')));
        $this->assertNotSame([], H5pLibraries::errors('H5P.Other', [], 'x'));
    }

    public function testAnswersMustAppearInTheCitedText(): void
    {
        $data = ['instruction' => 'Go', 'items' => [['text' => 'A {1} grind.', 'blanks' => [['answer' => 'velvet', 'alternatives' => [], 'tip' => '']]]]];
        $this->assertCount(1, H5pLibraries::unsupported('H5P.Blanks', $data, ['Espresso needs a fine grind.'], 'x'));
        $this->assertSame([], H5pLibraries::unsupported('H5P.Blanks', $data, ['The velvet texture comes from the milk.'], 'x'));
        $cards = ['title' => 'T', 'instruction' => 'i', 'cards' => [['front' => 'Crema', 'back' => 'The golden foam on top of an espresso', 'tip' => ''], ['front' => 'Dose', 'back' => 'Something unrelated entirely', 'tip' => '']]];
        $errors = H5pLibraries::unsupported('H5P.Dialogcards', $cards, ['Crema is the golden foam on top of an espresso shot.'], 'x');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('cards/1', $errors[0]);
    }

    public function testTheOutputSchemaOffersOnlyTheInstalledLibraries(): void
    {
        $schema = H5pLibraries::outputSchema(['H5P.Blanks', 'H5P.Dialogcards']);
        $choices = $schema['properties']['interaction']['anyOf'];
        $this->assertSame(['H5P.Blanks', 'H5P.Dialogcards'], array_map(fn ($c) => $c['properties']['library']['enum'][0], $choices));
    }
}
