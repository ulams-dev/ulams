<?php

namespace Ulams\CourseBuilder\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\CourseBuilder\StyleProfile\StyleProfileDiff;
use Ulams\CourseBuilder\StyleProfile\StyleProfileExtractor;
use Ulams\CourseBuilder\StyleProfile\StyleProfileSanitizer;

/** The sanitiser is what makes a style guide safe to read with a model (ADR 0196): out-of-schema values are dropped and reported. */
class StyleProfileSanitizerTest extends TestCase
{
    private const F1 = 'frg_aaaaaaaaaaaa';
    private const F2 = 'frg_bbbbbbbbbbbb';

    private function sanitizer(bool $requireCitations = true): StyleProfileSanitizer
    {
        return new StyleProfileSanitizer([self::F1 => true, self::F2 => true], ['Callout', 'WorkedExample', 'FlipCards'], $requireCitations);
    }

    public function testValidValuesKeepTheirCitations(): void
    {
        $r = $this->sanitizer()->sanitize([
            'voice' => ['person' => 'you', 'formality' => 3, 'sentenceLength' => 20, 'citations' => [self::F1]],
            'terminology' => [['use' => 'learner', 'avoid' => ['student', 'user'], 'citations' => [self::F2]]],
            'banned' => ['phrases' => [['phrase' => 'simply', 'citations' => [self::F1]]]],
            'readingLevel' => [['language' => 'en', 'level' => 'grade-8', 'citations' => [self::F1]], ['language' => 'pl', 'level' => 'B1', 'citations' => [self::F1]]],
            'components' => ['preferred' => ['Callout'], 'disallowed' => ['FlipCards'], 'citations' => [self::F1]],
            'imagery' => ['altTextRequired' => true, 'decorativeAllowed' => false, 'citations' => [self::F1]],
            'theme' => ['preset' => 'coffee', 'accent' => '#1D6F5C', 'fonts' => ['Inter'], 'citations' => [self::F1]],
            'examples' => [['text' => 'You start small.', 'citations' => [self::F1]]],
        ]);

        $this->assertSame([], $r['warnings']);
        $this->assertSame(1, $r['profile']['schemaVersion']);
        $this->assertSame([self::F1], $r['profile']['voice']['citations']);
        $this->assertSame(['student', 'user'], $r['profile']['terminology'][0]['avoid']);
        $this->assertFalse($r['profile']['banned']['blocking']);
        $this->assertSame('#1d6f5c', $r['profile']['theme']['accent']);
        $this->assertSame(['pl', 'en'], array_reverse(array_column($r['profile']['readingLevel'], 'language')));
    }

    public function testUnknownKeysOutOfRangeValuesAndUnknownComponentsAreDroppedWithWarnings(): void
    {
        $r = $this->sanitizer()->sanitize([
            'systemPrompt' => 'secret',
            'voice' => ['person' => 'admin', 'formality' => 99, 'sentenceLength' => 2, 'citations' => [self::F1]],
            'components' => ['preferred' => ['Exfiltrate', 'Callout'], 'disallowed' => ['Callout'], 'citations' => [self::F1]],
            'theme' => ['preset' => 'neon', 'accent' => 'red', 'fonts' => ['https://evil.example/font.woff2', 'Open Sans'], 'logoFileId' => '../../etc/passwd', 'citations' => [self::F1]],
            'readingLevel' => [['language' => 'english', 'level' => 'C3', 'citations' => [self::F1]]],
        ]);

        $this->assertArrayNotHasKey('systemPrompt', $r['profile']);
        $this->assertArrayNotHasKey('voice', $r['profile']);
        $this->assertArrayNotHasKey('readingLevel', $r['profile']);
        $this->assertSame(['disallowed' => ['Callout'], 'citations' => [self::F1]], $r['profile']['components']);
        $this->assertSame(['fonts' => ['Open Sans'], 'citations' => [self::F1]], $r['profile']['theme']);
        $text = implode("\n", $r['warnings']);
        foreach (['systemPrompt is not part', 'voice.person', 'voice.formality', 'voice.sentenceLength', 'not in the catalogue', 'theme.preset', 'theme.accent', 'web-font URLs', 'theme.logoFileId', 'readingLevel[0]', 'both preferred and disallowed'] as $needle) {
            $this->assertStringContainsString($needle, $text);
        }
    }

    public function testAModelValueWithoutACitationIsDroppedButAnAuthorsValueIsKept(): void
    {
        $values = ['terminology' => [['use' => 'learner', 'avoid' => ['student'], 'citations' => ['frg_cccccccccccc']]], 'banned' => ['phrases' => [['phrase' => 'just', 'citations' => []]]]];

        $model = $this->sanitizer(true)->sanitize($values);
        $this->assertSame(['schemaVersion' => 1], $model['profile']);
        $this->assertStringContainsString('frg_cccccccccccc, which is not a fragment of the style guide', implode("\n", $model['warnings']));
        $this->assertStringContainsString('cites no passage of the style guide and was dropped', implode("\n", $model['warnings']));

        $author = $this->sanitizer(false)->sanitize($values);
        $this->assertSame([], $author['profile']['terminology'][0]['citations']);
        $this->assertSame('just', $author['profile']['banned']['phrases'][0]['phrase']);
    }

    public function testStyleValuesNeverCarryLinksOrMarkup(): void
    {
        $r = $this->sanitizer(false)->sanitize([
            'banned' => ['phrases' => [['phrase' => 'see https://evil.example', 'citations' => []], ['phrase' => '<b>bold</b>', 'citations' => []], ['phrase' => 'ok phrase', 'citations' => []]]],
            'examples' => [['text' => 'Visit www.evil.example now', 'citations' => []], ['text' => 'Fine.', 'citations' => []]],
        ]);

        $this->assertSame(['ok phrase'], array_column($r['profile']['banned']['phrases'], 'phrase'));
        $this->assertSame(['Fine.'], array_column($r['profile']['examples'], 'text'));
    }

    public function testExtractorTurnsTheModelsEmptySentinelsIntoAbsentValues(): void
    {
        $out = StyleProfileExtractor::fromModel([
            'theme' => ['preset' => '', 'accent' => '', 'secondary' => '', 'fonts' => [], 'citations' => []],
            'voice' => ['person' => 'you', 'formality' => 0, 'sentenceLength' => 18, 'citations' => [self::F1]],
            'imagery' => ['altTextRequired' => 'yes', 'decorativeAllowed' => 'no', 'noTextInImages' => 'unspecified', 'citations' => [self::F1]],
            'terminology' => [['use' => 'no', 'avoid' => ['yes'], 'citations' => [self::F1]]],
        ]);

        $this->assertSame(['fonts' => [], 'citations' => []], $out['theme']);
        $this->assertSame(['person' => 'you', 'sentenceLength' => 18, 'citations' => [self::F1]], $out['voice']);
        $this->assertSame(['altTextRequired' => true, 'decorativeAllowed' => false, 'citations' => [self::F1]], $out['imagery']);
        $this->assertSame('no', $out['terminology'][0]['use']);
    }

    public function testDiffListsAddedChangedAndRemovedValuesWithTheirCitations(): void
    {
        $before = ['schemaVersion' => 1, 'voice' => ['formality' => 2, 'citations' => []], 'terminology' => [['use' => 'student', 'avoid' => [], 'citations' => []]]];
        $after = ['schemaVersion' => 1, 'voice' => ['formality' => 3, 'citations' => [self::F1]], 'terminology' => [['use' => 'learner', 'avoid' => ['student'], 'citations' => [self::F2]]]];

        $byPath = [];
        foreach (StyleProfileDiff::between($before, $after) as $change) {
            $byPath[$change['path']] = $change;
        }
        $this->assertSame('changed', $byPath['voice.formality']['change']);
        $this->assertSame([self::F1], $byPath['voice.formality']['citations']);
        $this->assertSame('added', $byPath['terminology.learner']['change']);
        $this->assertSame('removed', $byPath['terminology.student']['change']);
        $this->assertSame([], StyleProfileDiff::between($after, $after));
    }
}
