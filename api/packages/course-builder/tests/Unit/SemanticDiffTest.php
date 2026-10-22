<?php

namespace Ulams\CourseBuilder\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\CourseBuilder\Blueprint\SemanticDiff;

/** ADR 0192, CV-2: moves, reorders, citations, quiz semantics, bounded text and the summary of the semantic diff. */
class SemanticDiffTest extends TestCase
{
    private const FA = 'frg_aaaaaaaaaaaa';
    private const FB = 'frg_bbbbbbbbbbbb';
    private const FC = 'frg_cccccccccccc';

    private function question(array $overrides = []): array
    {
        return array_replace([
            'id' => 'q_1', 'type' => 'single', 'stem' => 'Pick one', 'explanation' => 'because', 'citations' => [self::FA],
            'options' => [['id' => 'o_a', 'text' => 'A', 'correct' => true], ['id' => 'o_b', 'text' => 'B', 'correct' => false]],
        ], $overrides);
    }

    private function lesson(string $id, array $overrides = []): array
    {
        return array_replace([
            'id' => $id, 'title' => "Lesson {$id}", 'summary' => 's', 'minutes' => 5, 'citations' => [self::FA],
            'blocks' => [['id' => "b_{$id}", 'kind' => 'text', 'markdown' => 'one two three four five six seven eight nine ten', 'citations' => [self::FA]]],
        ], $overrides);
    }

    /** @param array<int,array> $modules */
    private function doc(array $modules): array
    {
        return ['schemaVersion' => 3, 'course' => ['id' => 'crs_1', 'title' => 'Course'], 'modules' => $modules];
    }

    private function mod(string $id, array $lessons): array
    {
        return ['id' => $id, 'title' => "Module {$id}", 'summary' => 's', 'lessons' => $lessons];
    }

    /** @return array<string,array> entries keyed by element id */
    private function byId(array $diff): array
    {
        return array_column($diff['changes'], null, 'id');
    }

    public function testNothingChangedIsAnEmptyDiff(): void
    {
        $doc = $this->doc([$this->mod('m1', [$this->lesson('l1')])]);

        $diff = SemanticDiff::compare($doc, $doc);

        $this->assertSame(1, $diff['diffVersion']);
        $this->assertSame([], $diff['changes']);
        $this->assertSame([], $diff['quiz']);
        $this->assertSame('No change.', $diff['summary']['text']);
        $this->assertSame(0, $diff['summary']['counts']['changed']);
    }

    public function testALessonMovedToAnotherModuleIsMovedNotRemovedAndAdded(): void
    {
        $from = $this->doc([$this->mod('m1', [$this->lesson('l1'), $this->lesson('l2')]), $this->mod('m2', [$this->lesson('l3')])]);
        $to = $this->doc([$this->mod('m1', [$this->lesson('l1')]), $this->mod('m2', [$this->lesson('l3'), $this->lesson('l2')])]);

        $diff = SemanticDiff::compare($from, $to);
        $e = $this->byId($diff);

        $this->assertSame('moved', $e['l2']['kind']);
        $this->assertSame(['id' => 'm1', 'label' => 'Module 1'], $e['l2']['moved']['from']);
        $this->assertSame(['id' => 'm2', 'label' => 'Module 2'], $e['l2']['moved']['to']);
        $this->assertSame('Lesson 2.2 moved from Module 1 to Module 2.', $e['l2']['description']);
        $this->assertSame(['moved' => 1, 'added' => 0, 'removed' => 0], ['moved' => $diff['summary']['counts']['moved'], 'added' => $diff['summary']['counts']['added'], 'removed' => $diff['summary']['counts']['removed']]);
        $this->assertStringContainsString('1 lesson moved', $diff['summary']['text']);
        $this->assertNull($e['l1'] ?? null, 'a lesson that stayed is not listed');
    }

    public function testASiblingOutsideTheLongestCommonSubsequenceIsReordered(): void
    {
        $from = $this->doc([$this->mod('m1', [$this->lesson('l1'), $this->lesson('l2'), $this->lesson('l3'), $this->lesson('l4')])]);
        $to = $this->doc([$this->mod('m1', [$this->lesson('l4'), $this->lesson('l1'), $this->lesson('l2'), $this->lesson('l3')])]);

        $diff = SemanticDiff::compare($from, $to);

        $this->assertSame(['l4'], array_keys($this->byId($diff)), 'one lesson jumped to the front; the other three kept their order');
        $e = $this->byId($diff)['l4'];
        $this->assertSame('reordered', $e['kind']);
        $this->assertSame(['parentId' => 'm1', 'from' => 4, 'to' => 1], $e['reordered']);
        $this->assertNull($e['moved']);
    }

    public function testAMovedLessonThatWasAlsoEditedIsChangedAndMoved(): void
    {
        $from = $this->doc([$this->mod('m1', [$this->lesson('l1')]), $this->mod('m2', [])]);
        $to = $this->doc([$this->mod('m1', []), $this->mod('m2', [$this->lesson('l1', ['title' => 'Renamed'])])]);

        $e = $this->byId(SemanticDiff::compare($from, $to))['l1'];

        $this->assertSame('changed', $e['kind']);
        $this->assertNotNull($e['moved']);
        $this->assertSame(['title'], array_column($e['fields'], 'field'));
        $this->assertStringContainsString('moved from Module 1 to Module 2; changed title', $e['description']);
    }

    public function testCitationsAreComparedAsSetsAndRepointedFragmentsAreMatched(): void
    {
        $from = $this->doc([$this->mod('m1', [$this->lesson('l1', ['citations' => [self::FA, self::FB]])])]);
        $to = $this->doc([$this->mod('m1', [$this->lesson('l1', ['citations' => [self::FC, self::FB]])])]);

        $plain = $this->byId(SemanticDiff::compare($from, $to))['l1']['citations'];
        $this->assertSame([[self::FC], [self::FA], []], [$plain['added'], $plain['removed'], $plain['repointed']]);

        $diff = SemanticDiff::compare($from, $to, [self::FA => self::FC]);
        $c = $this->byId($diff)['l1']['citations'];
        $this->assertSame([[], [], [['from' => self::FA, 'to' => self::FC]]], [$c['added'], $c['removed'], $c['repointed']]);
        $this->assertSame(['added' => 0, 'removed' => 0, 'repointed' => 1], $diff['summary']['citations']);
        $this->assertEqualsCanonicalizing([self::FA, self::FC], SemanticDiff::citedIds($diff));
        $this->assertStringContainsString('1 citation repointed', $diff['summary']['text']);

        $order = $this->doc([$this->mod('m1', [$this->lesson('l1', ['citations' => [self::FB, self::FA]])])]);
        $same = $this->doc([$this->mod('m1', [$this->lesson('l1', ['citations' => [self::FA, self::FB]])])]);
        $this->assertNull($this->byId(SemanticDiff::compare($same, $order))['l1']['citations'] ?? null, 'the order of citations is not a change');
    }

    public function testACitationOfARepointedFragmentThatIsNotInTheNewSetStaysRemoved(): void
    {
        $from = $this->doc([$this->mod('m1', [$this->lesson('l1', ['citations' => [self::FA]])])]);
        $to = $this->doc([$this->mod('m1', [$this->lesson('l1', ['citations' => [self::FB]])])]);

        $c = $this->byId(SemanticDiff::compare($from, $to, [self::FA => self::FC]))['l1']['citations'];

        $this->assertSame([[self::FB], [self::FA], []], [$c['added'], $c['removed'], $c['repointed']]);
    }

    private function withQuestion(array $question): array
    {
        return $this->doc([$this->mod('m1', [$this->lesson('l1', ['quiz' => ['id' => 'qz_1', 'questions' => [$question]]])])]);
    }

    public function testAChangedCorrectAnswerIsAnAnswerKeyChangeWithItsConsequence(): void
    {
        $flipped = $this->question(['options' => [['id' => 'o_a', 'text' => 'A', 'correct' => false], ['id' => 'o_b', 'text' => 'B', 'correct' => true]]]);

        $diff = SemanticDiff::compare($this->withQuestion($this->question()), $this->withQuestion($flipped));

        $this->assertCount(1, $diff['quiz']);
        $q = $diff['quiz'][0];
        $this->assertSame('q_1', $q['questionId']);
        $this->assertSame('Lesson 1.1 › Q1', $q['label']);
        $this->assertTrue($q['answerKeyChanged']);
        $this->assertFalse($q['wording']);
        $this->assertSame([['A'], ['B']], [$q['correctBefore'], $q['correctAfter']]);
        $this->assertCount(2, $q['options']['keyChanged']);
        $this->assertStringContainsString('re-attempt notice', $q['consequence']);
        $this->assertSame(1, $diff['summary']['quiz']['answerKeysChanged']);
        $this->assertSame(1, $diff['summary']['learnerImpact']['reattemptNotices']);
        $this->assertStringContainsString('1 answer key changed', $diff['summary']['text']);
    }

    public function testAddingACorrectOptionChangesTheKeyAndAddingAWrongOneDoesNot(): void
    {
        $wrong = $this->question(['options' => [...$this->question()['options'], ['id' => 'o_c', 'text' => 'C', 'correct' => false]]]);
        $right = $this->question(['options' => [...$this->question()['options'], ['id' => 'o_c', 'text' => 'C', 'correct' => true]]]);

        $a = SemanticDiff::compare($this->withQuestion($this->question()), $this->withQuestion($wrong))['quiz'][0];
        $b = SemanticDiff::compare($this->withQuestion($this->question()), $this->withQuestion($right))['quiz'][0];

        $this->assertFalse($a['answerKeyChanged']);
        $this->assertSame([['id' => 'o_c', 'text' => 'C', 'correct' => false]], $a['options']['added']);
        $this->assertTrue($b['answerKeyChanged']);
    }

    public function testWordingEditsWithoutAKeyChangeAreWording(): void
    {
        $edited = $this->question(['stem' => 'Pick exactly one', 'options' => [['id' => 'o_a', 'text' => 'Alpha', 'correct' => true], ['id' => 'o_b', 'text' => 'B', 'correct' => false]]]);

        $diff = SemanticDiff::compare($this->withQuestion($this->question()), $this->withQuestion($edited));

        $q = $diff['quiz'][0];
        $this->assertFalse($q['answerKeyChanged']);
        $this->assertTrue($q['wording']);
        $this->assertSame(['before' => 'Pick one', 'after' => 'Pick exactly one'], $q['stem']);
        $this->assertSame([['id' => 'o_a', 'before' => 'A', 'after' => 'Alpha']], $q['options']['reworded']);
        $this->assertNull($q['consequence']);
        $this->assertSame(['answerKeysChanged' => 0, 'reworded' => 1, 'questionsAdded' => 0, 'questionsRemoved' => 0], $diff['summary']['quiz']);
    }

    public function testAnAddedOrRemovedQuestionIsCountedButHasNoQuizEntry(): void
    {
        $diff = SemanticDiff::compare($this->doc([$this->mod('m1', [$this->lesson('l1')])]), $this->withQuestion($this->question()));

        $this->assertSame([], $diff['quiz']);
        $this->assertSame(1, $diff['summary']['quiz']['questionsAdded']);
        $this->assertSame(1, $diff['summary']['types']['question']['added']);
        $this->assertArrayNotHasKey('lesson', $diff['summary']['types'], 'the lesson itself did not change');
    }

    public function testAVeryLongFieldIsSentAsALineDiffNotWhole(): void
    {
        $line = str_repeat('x', 100);
        $long = implode("\n", array_fill(0, 300, $line));
        $from = $this->doc([$this->mod('m1', [$this->lesson('l1', ['blocks' => [['id' => 'b1', 'kind' => 'text', 'markdown' => $long, 'citations' => [self::FA]]]])])]);
        $to = $this->doc([$this->mod('m1', [$this->lesson('l1', ['blocks' => [['id' => 'b1', 'kind' => 'text', 'markdown' => $long . "\nA new last line", 'citations' => [self::FA]]]])])]);
        $this->assertGreaterThan(SemanticDiff::LONG_FIELD, strlen($long));

        $field = $this->byId(SemanticDiff::compare($from, $to))['b1']['fields'][0];

        $this->assertTrue($field['truncated']);
        $this->assertNull($field['before']);
        $this->assertNull($field['after']);
        $this->assertSame([[], ['A new last line'], 0], [$field['lineDiff']['removed'], $field['lineDiff']['added'], $field['lineDiff']['omitted']]);
    }

    public function testShortFieldsStayWhole(): void
    {
        $from = $this->doc([$this->mod('m1', [$this->lesson('l1')])]);
        $to = $this->doc([$this->mod('m1', [$this->lesson('l1', ['title' => 'New'])])]);

        $field = $this->byId(SemanticDiff::compare($from, $to))['l1']['fields'][0];

        $this->assertSame(['title', 'Lesson l1', 'New'], [$field['field'], $field['before'], $field['after']]);
        $this->assertArrayNotHasKey('truncated', $field);
    }

    public function testLearnerImpactUsesTheMinorAndMajorRuleOfLivingCourse(): void
    {
        $text = 'one two three four five six seven eight nine ten';
        $block = fn (string $id, string $markdown) => ['id' => $id, 'kind' => 'text', 'markdown' => $markdown, 'citations' => [self::FA]];
        $lessonWith = fn (string $id, string $markdown) => $this->lesson($id, ['blocks' => [$block("b_{$id}", $markdown)]]);
        $from = $this->doc([$this->mod('m1', [$lessonWith('minor', $text), $lessonWith('major', $text), $lessonWith('untouched', $text), $lessonWith('gone', $text)])]);
        $to = $this->doc([$this->mod('m1', [
            $lessonWith('minor', 'one two three four five six seven eight nine eleven'),
            $lessonWith('major', 'completely different words about another subject entirely'),
            $lessonWith('untouched', $text),
            $lessonWith('fresh', $text),
        ])]);

        $impact = SemanticDiff::compare($from, $to)['summary']['learnerImpact'];

        $this->assertSame(1, $impact['lessonsWithUpdatedNotice']);
        $this->assertSame(1, $impact['lessonsChangedSilently']);
        $this->assertSame(3, $impact['lessonsKeepingCompletion']);
        $this->assertSame(1, $impact['lessonsRemoved']);
        $this->assertSame(1, $impact['lessonsAdded']);
        $this->assertGreaterThan(0.85, SemanticDiff::similarity($text, 'one two three four five six seven eight nine eleven'));
        $this->assertLessThan(0.85, SemanticDiff::similarity($text, 'completely different words'));
    }

    public function testACitationOnlyChangeIsSilentForLearners(): void
    {
        $from = $this->doc([$this->mod('m1', [$this->lesson('l1')])]);
        $to = $this->doc([$this->mod('m1', [$this->lesson('l1', ['citations' => [self::FB]])])]);

        $impact = SemanticDiff::compare($from, $to)['summary']['learnerImpact'];

        $this->assertSame(0, $impact['lessonsWithUpdatedNotice']);
        $this->assertSame(1, $impact['lessonsChangedSilently']);
    }

    public function testTheFirstReleaseSummaryNamesTheLessonsAdded(): void
    {
        $diff = SemanticDiff::compare([], $this->doc([$this->mod('m1', [$this->lesson('l1'), $this->lesson('l2')])]));

        $this->assertTrue($diff['summary']['first']);
        $this->assertSame('2 lessons added (the first release).', $diff['summary']['text']);
    }

    public function testEveryChangeCarriesItsPlaceInTheTreeWithTheTitlesOfItsUnchangedAncestors(): void
    {
        $from = $this->doc([$this->mod('m1', [$this->lesson('l1'), $this->lesson('l2')])]);
        $to = $from;
        $to['modules'][0]['lessons'][0]['blocks'][0]['markdown'] = 'changed text';
        array_pop($to['modules'][0]['lessons']);

        $by = $this->byId(SemanticDiff::compare($from, $to));

        $this->assertNull($by['b_l1']['title'], 'a block has no title');
        $this->assertSame([
            ['id' => 'm1', 'type' => 'module', 'label' => 'Module 1', 'title' => 'Module m1'],
            ['id' => 'l1', 'type' => 'lesson', 'label' => 'Lesson 1.1', 'title' => 'Lesson l1'],
        ], $by['b_l1']['path']);
        // a removed element keeps the place it had
        $this->assertSame(['m1'], array_column($by['l2']['path'], 'id'));
        $this->assertSame('Lesson l2', $by['l2']['title']);
    }
}
