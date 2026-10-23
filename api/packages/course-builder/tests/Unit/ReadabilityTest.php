<?php

namespace Ulams\CourseBuilder\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\CourseBuilder\StyleProfile\Readability;

/** The readability formulas are crude hints (ADR 0196): they must rank plain text below dense text and say nothing about short text. */
class ReadabilityTest extends TestCase
{
    private const EASY_EN = 'You start with one small task. Then you build on it. Each step is short. Run the command and read the result. If it looks wrong, try again. Keep going until it works. It is a good way to learn.';
    private const HARD_EN = 'Notwithstanding considerable organisational heterogeneity, implementation methodologies necessitate comprehensive standardisation of interdependent administrative responsibilities, whereas fragmentary experimentation characteristically undermines institutional accountability and predictability across geographically distributed environments.';
    private const EASY_PL = 'Zacznij od jednego zadania. Potem dodawaj kolejne. Każdy krok jest krótki. Uruchom polecenie i sprawdź wynik. Jeśli coś jest nie tak, spróbuj jeszcze raz. To dobry sposób nauki.';
    private const HARD_PL = 'Mimo znacznego zróżnicowania organizacyjnego, metodologie wdrożeniowe wymagają kompleksowej standaryzacji współzależnych obowiązków administracyjnych, podczas gdy fragmentaryczne eksperymentowanie systematycznie podważa odpowiedzialność instytucjonalną oraz przewidywalność funkcjonowania rozproszonych przedsiębiorstw.';

    private function grade(string $language, string $text): ?float
    {
        return Readability::grade($language, Readability::sentences($text))['grade'] ?? null;
    }

    public function testEnglishDenseTextGradesHigherThanPlainText(): void
    {
        $easy = $this->grade('en', self::EASY_EN . ' ' . self::EASY_EN);
        $hard = $this->grade('en', self::HARD_EN . ' ' . self::HARD_EN . ' ' . self::HARD_EN);
        $this->assertNotNull($easy);
        $this->assertNotNull($hard);
        $this->assertLessThan(6, $easy);
        $this->assertGreaterThan(14, $hard);
    }

    public function testPolishUsesThePisarekIndex(): void
    {
        $easy = $this->grade('pl', self::EASY_PL . ' ' . self::EASY_PL);
        $hard = $this->grade('pl', self::HARD_PL . ' ' . self::HARD_PL . ' ' . self::HARD_PL);
        $this->assertNotNull($easy);
        $this->assertNotNull($hard);
        $this->assertLessThan(7, $easy);
        $this->assertGreaterThan(14, $hard);
        $this->assertSame(1, Readability::syllablesPl('wiatr'));
        $this->assertSame(3, Readability::syllablesPl('kreacja'), 'cja is one syllable: kre-a-cja');
    }

    public function testChineseUsesACharacterCountHeuristic(): void
    {
        $short = str_repeat('你好吗。我很好。我们开始吧。', 4);
        $long = str_repeat('这是一个包含许多不同概念和复杂修饰成分的非常长的句子需要读者反复阅读才能理解其含义。', 3);
        $easy = $this->grade('zh', $short);
        $hard = $this->grade('zh', $long);
        $this->assertNotNull($easy);
        $this->assertNotNull($hard);
        $this->assertLessThan(6, $easy, 'short sentences are easy');
        $this->assertGreaterThan(10, $hard);
    }

    public function testShortTextAndUnknownLanguagesSayNothing(): void
    {
        $this->assertNull($this->grade('en', 'One. Two. Three.'));
        $this->assertNull($this->grade('en', 'A single long sentence without a full stop that goes on for a while but has no second sentence at all'));
        $this->assertNull($this->grade('de', self::EASY_EN . ' ' . self::EASY_EN));
        $this->assertFalse(Readability::supports('fr'));
        $this->assertTrue(Readability::supports('pl'));
    }

    public function testTargetsAreGradesOrCefrLevels(): void
    {
        $this->assertSame(8.0, Readability::targetGrade('grade-8'));
        $this->assertSame(7.0, Readability::targetGrade('B1'));
        $this->assertSame(16.0, Readability::targetGrade('C2'));
        $this->assertNull(Readability::targetGrade('C3'));
        $this->assertNull(Readability::targetGrade('easy'));
    }

    public function testSentencesDropMarkdownSyntaxCodeLinksAndImages(): void
    {
        $markdown = "## Title\n\nRun `npm test` now. See [the docs](https://example.test/x) for more.\n\n![diagram](a.png)\n\n- first point.\n- second point.\n\n```\nnot prose. at all.\n```\n";
        $sentences = Readability::sentences($markdown);
        $this->assertStringNotContainsString('npm', implode(' ', $sentences));
        $this->assertStringNotContainsString('example.test', implode(' ', $sentences));
        $this->assertStringNotContainsString('not prose', implode(' ', $sentences));
        $this->assertContains('See the docs for more.', $sentences);
        $this->assertContains('first point.', $sentences);
    }
}
