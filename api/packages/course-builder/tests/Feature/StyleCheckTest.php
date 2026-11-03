<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\CourseBuilder\Models\StyleProfile;
use Ulams\CourseBuilder\StyleProfile\StyleCheck;
use Ulams\CourseBuilder\Tests\TestCase;

/** The style check per rule and per language (ADR 0196). Deterministic: no model, nothing rewritten. */
class StyleCheckTest extends TestCase
{
    private const LESSON = '01lessonaaaaaaaaaaaaaaaaaa';

    private function doc(array $blocks, string $language = 'en', array $extra = []): array
    {
        return [
            'schemaVersion' => 3,
            'course' => ['id' => '01courseaaaaaaaaaaaaaaaaaa', 'title' => 'Brewing', 'language' => $language, 'objectives' => []],
            'modules' => [['id' => '01moduleaaaaaaaaaaaaaaaaaa', 'title' => 'M', 'lessons' => [['id' => self::LESSON, 'title' => 'Grinding', 'blocks' => array_map(
                fn ($md, $i) => ['id' => sprintf('01blockaaaaaaaaaaaaaaaaa%02d', $i), 'kind' => 'paragraph', 'markdown' => $md, 'citations' => []],
                $blocks, array_keys($blocks)
            )] + $extra]]],
            'finalTest' => null,
            'pages' => ['landing' => null, 'header' => null],
        ];
    }

    private function profile(array $values): StyleProfile
    {
        return new StyleProfile(['id' => '01profileaaaaaaaaaaaaaaaaa', 'name' => 'Test', 'profile' => ['schemaVersion' => 1] + $values]);
    }

    private function check(array $doc, array $values, string $language = 'en'): array
    {
        return (new StyleCheck())->run($doc, $this->profile($values), $language);
    }

    public function testABannedPhraseWarnsAndBlocksOnlyWhenTheProfileSaysSo(): void
    {
        $doc = $this->doc(['Simply grind the beans. It is Obviously fine.']);
        $phrases = [['phrase' => 'simply', 'citations' => []], ['phrase' => 'obviously', 'citations' => []], ['phrase' => 'just', 'citations' => []]];

        $warn = $this->check($doc, ['banned' => ['blocking' => false, 'phrases' => $phrases]]);
        $this->assertSame([], $warn['blocking']);
        $this->assertSame(['simply', 'obviously'], array_column($warn['warnings'], 'term'));
        $this->assertSame('style_banned_warning', $warn['warnings'][0]['code']);

        $block = $this->check($doc, ['banned' => ['blocking' => true, 'phrases' => $phrases]]);
        $this->assertSame(['simply', 'obviously'], array_column($block['blocking'], 'term'));
        $this->assertSame([], $block['warnings']);
        $this->assertSame('style_banned', $block['blocking'][0]['code']);
        $this->assertSame('01blockaaaaaaaaaaaaaaaaa00', $block['blocking'][0]['elementId']);
        $this->assertStringContainsString('Simply grind', $block['blocking'][0]['quote']);
        $this->assertStringContainsString('“Grinding”: “simply” is a banned phrase', $block['blocking'][0]['message']);
    }

    public function testWholeWordsOnlyAndPhrasesWithPunctuationOrUnicode(): void
    {
        $doc = $this->doc(['Justice is not a reason. We will cut corners? No way. Najlepsza kawa na świecie.']);
        $r = $this->check($doc, ['banned' => ['blocking' => true, 'phrases' => [['phrase' => 'just', 'citations' => []], ['phrase' => 'cut corners', 'citations' => []], ['phrase' => 'na świecie', 'citations' => []], ['phrase' => 'kaw', 'citations' => []]]]]);
        $this->assertSame(['cut corners', 'na świecie'], array_column($r['blocking'], 'term'), '"just" is not inside "Justice" and "kaw" is not inside "kawa"');
    }

    public function testTerminologyAndProductNameCasing(): void
    {
        $doc = $this->doc(['Each student opens Northwind Academy. The northwind academy site and NWA help every user.']);
        $r = $this->check($doc, ['terminology' => [
            ['use' => 'learner', 'avoid' => ['student', 'user'], 'citations' => []],
            ['use' => 'Northwind Academy', 'avoid' => ['northwind academy', 'NWA'], 'citations' => []],
        ]]);
        $terms = array_column($r['warnings'], 'term');
        $this->assertContains('student', $terms);
        $this->assertContains('user', $terms);
        $this->assertContains('northwind academy', $terms);
        $this->assertContains('NWA', $terms);
        $this->assertSame(['Northwind Academy', 'Northwind Academy'], array_values(array_filter(array_column($r['warnings'], 'use'), fn ($u) => $u === 'Northwind Academy')), 'both findings say what to write');
        $this->assertSame([], $r['blocking']);

        // without the wrong spelling in the avoid list the casing rule reports it
        $only = $this->check($this->doc(['Open northwind academy today.']), ['terminology' => [['use' => 'Northwind Academy', 'avoid' => [], 'citations' => []]]]);
        $this->assertSame(['casing'], array_column($only['warnings'], 'rule'));
        $this->assertSame('northwind academy', $only['warnings'][0]['term']);
        $this->assertSame('Northwind Academy', $only['warnings'][0]['use']);

        $clean = $this->check($this->doc(['Every learner opens Northwind Academy.']), ['terminology' => [
            ['use' => 'learner', 'avoid' => ['student'], 'citations' => []],
            ['use' => 'Northwind Academy', 'avoid' => ['northwind academy'], 'citations' => []],
        ]]);
        $this->assertSame([], $clean['warnings'], 'the right spelling is not flagged by the wrong one');
    }

    public function testFindingsCarryThePassagesTheyComeFrom(): void
    {
        $guide = $this->actingAs($this->author(), 'api')->post('/api/admin/course-builder/style-profiles', ['file' => $this->fixture('style-guide.md')], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $cite = $guide['profile']['banned']['phrases'][0]['citations'][0];
        $r = $this->check($this->doc(['Simply do it.']), ['banned' => ['blocking' => true, 'phrases' => [['phrase' => 'simply', 'citations' => [$cite]]]]]);
        $this->assertSame([['id' => $cite, 'label' => '§4 Banned phrases']], $r['blocking'][0]['citations']);
        $this->assertStringEndsWith('(style guide §4 Banned phrases)', $r['blocking'][0]['message']);
    }

    public function testQuizQuestionsAndTheCourseMetadataAreChecked(): void
    {
        $doc = $this->doc(['Fine text.'], 'en', ['quiz' => ['questions' => [['id' => '01questionaaaaaaaaaaaaaaaa', 'stem' => 'Why is it obviously better?', 'options' => [['text' => 'Because', 'correct' => true]], 'citations' => []]]]]);
        $doc['course']['description'] = 'Just brew.';
        $r = $this->check($doc, ['banned' => ['blocking' => true, 'phrases' => [['phrase' => 'obviously', 'citations' => []], ['phrase' => 'just', 'citations' => []]]]]);
        $this->assertEqualsCanonicalizing(['01questionaaaaaaaaaaaaaaaa', '01courseaaaaaaaaaaaaaaaaaa'], array_column($r['blocking'], 'elementId'));
    }

    public function testImagesNeedAltTextOnlyWhenTheProfileRequiresIt(): void
    {
        $doc = $this->doc(["Look:\n\n![](a.png)\n\n```\n![](in-code.png)\n```"]);
        $this->assertSame([], $this->check($doc, ['imagery' => ['altTextRequired' => false, 'citations' => []]])['warnings']);
        $r = $this->check($doc, ['imagery' => ['altTextRequired' => true, 'citations' => []]]);
        $this->assertSame(['style_alt_text'], array_column($r['warnings'], 'code'));
        $this->assertSame([], $this->check($this->doc(['![A grinder](a.png)']), ['imagery' => ['altTextRequired' => true, 'citations' => []]])['warnings']);
    }

    public function testDisallowedComponentsOfTheLandingPageWarn(): void
    {
        $doc = $this->doc(['Text.']);
        $doc['pages']['landing'] = ['component' => 'Page', 'children' => [['component' => 'Hero'], ['component' => 'FlipCards', 'children' => [['component' => 'FlipCards']]]]];
        $r = $this->check($doc, ['components' => ['disallowed' => ['FlipCards', 'Marquee'], 'citations' => []]]);
        $this->assertSame(['FlipCards'], array_column($r['warnings'], 'term'));
        $this->assertSame('style_component', $r['warnings'][0]['code']);
    }

    public function testReadingLevelAndSentenceLengthAreHintsNeverBlocking(): void
    {
        $dense = 'Notwithstanding considerable organisational heterogeneity, implementation methodologies necessitate comprehensive standardisation of interdependent administrative responsibilities, whereas fragmentary experimentation characteristically undermines institutional accountability and predictability across geographically distributed environments.';
        $doc = $this->doc([$dense . ' ' . $dense . ' ' . $dense]);
        $r = $this->check($doc, [
            'readingLevel' => [['language' => 'en', 'level' => 'grade-8', 'citations' => []], ['language' => 'pl', 'level' => 'B1', 'citations' => []]],
            'voice' => ['sentenceLength' => 20, 'citations' => []],
            'banned' => ['blocking' => true, 'phrases' => [['phrase' => 'zzz', 'citations' => []]]],
        ]);
        $this->assertSame([], $r['blocking']);
        $this->assertSame([], $r['warnings']);
        $this->assertEqualsCanonicalizing(['style_reading_level', 'style_sentence_length'], array_column($r['hints'], 'code'));
        $this->assertStringContainsString('asks for grade-8', $r['hints'][0]['message'] . $r['hints'][1]['message']);

        // plain text meets the target; a language without a rule for it is not judged; short text is not judged
        $plain = $this->doc([str_repeat('You start with one small task. Then you build on it. ', 6)]);
        $this->assertSame([], $this->check($plain, ['readingLevel' => [['language' => 'en', 'level' => 'grade-8', 'citations' => []]], 'voice' => ['sentenceLength' => 20, 'citations' => []]])['hints']);
        $this->assertSame([], $this->check($doc, ['readingLevel' => [['language' => 'pl', 'level' => 'A1', 'citations' => []]]])['hints']);
        $this->assertSame([], $this->check($this->doc(['Short one.']), ['readingLevel' => [['language' => 'en', 'level' => 'A1', 'citations' => []]]])['hints']);
    }

    public function testPolishCourseUsesThePolishTarget(): void
    {
        $dense = 'Mimo znacznego zróżnicowania organizacyjnego, metodologie wdrożeniowe wymagają kompleksowej standaryzacji współzależnych obowiązków administracyjnych, podczas gdy fragmentaryczne eksperymentowanie systematycznie podważa odpowiedzialność instytucjonalną oraz przewidywalność funkcjonowania rozproszonych przedsiębiorstw.';
        $r = $this->check($this->doc([$dense . ' ' . $dense . ' ' . $dense], 'pl'), ['readingLevel' => [['language' => 'pl', 'level' => 'B1', 'citations' => []], ['language' => 'en', 'level' => 'A1', 'citations' => []]]]);
        $this->assertSame(['style_reading_level'], array_column($r['hints'], 'code'));
        $this->assertStringContainsString('B1', $r['hints'][0]['message']);
    }

    public function testAVariantPrefixLeadsTheMessage(): void
    {
        $r = (new StyleCheck())->run($this->doc(['Simply.']), $this->profile(['banned' => ['blocking' => true, 'phrases' => [['phrase' => 'simply', 'citations' => []]]]]), 'en', 'Variant “Expert”: ');
        $this->assertStringStartsWith('Variant “Expert”: “Grinding”', $r['blocking'][0]['message']);
    }
}
