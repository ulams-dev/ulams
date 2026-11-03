<?php

namespace Ulams\CourseBuilder\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\CourseBuilder\Services\ReleaseRecorder;

/** The counts a release shows in the list (ADR 0192): what changed since the previous release. */
class ReleaseSummaryTest extends TestCase
{
    /** @return array<string,mixed> */
    private function doc(array $lessonExtra = [], bool $secondLesson = true, bool $correctA = true, array $citations = ['frg_aaaaaaaaaaaa']): array
    {
        $lessons = [array_merge([
            'id' => 'les_1', 'title' => 'Intro', 'summary' => 's', 'minutes' => 5, 'citations' => $citations,
            'blocks' => [['id' => 'blk_1', 'kind' => 'text', 'markdown' => 'Hello', 'citations' => $citations]],
            'quiz' => ['id' => 'qz_1', 'questions' => [[
                'id' => 'q_1', 'type' => 'single', 'stem' => 'Pick one', 'explanation' => 'because', 'citations' => $citations,
                'options' => [['id' => 'o_a', 'text' => 'A', 'correct' => $correctA], ['id' => 'o_b', 'text' => 'B', 'correct' => !$correctA]],
            ]]],
        ], $lessonExtra)];
        if ($secondLesson) {
            $lessons[] = ['id' => 'les_2', 'title' => 'Next', 'summary' => 's', 'minutes' => 5, 'blocks' => []];
        }

        return ['schemaVersion' => 3, 'course' => ['id' => 'crs_1', 'title' => 'Course'], 'modules' => [['id' => 'mod_1', 'title' => 'M', 'summary' => 's', 'lessons' => $lessons]]];
    }

    public function testTheFirstReleaseCountsEverythingAsAdded(): void
    {
        $summary = ReleaseRecorder::summary([], $this->doc());

        $this->assertTrue($summary['first']);
        $this->assertSame(0, $summary['counts']['changed']);
        $this->assertSame(0, $summary['counts']['removed']);
        $this->assertSame(['added' => 2, 'changed' => 0, 'removed' => 0], $summary['lessons']);
        $this->assertSame(['added' => 1, 'changed' => 0, 'removed' => 0], $summary['questions']);
        $this->assertSame(0, $summary['answerKeysChanged']);
    }

    public function testChangedAndRemovedElementsAreCountedByType(): void
    {
        $summary = ReleaseRecorder::summary($this->doc(), $this->doc(['title' => 'Intro, rewritten'], false));

        $this->assertFalse($summary['first']);
        $this->assertSame(['added' => 0, 'changed' => 1, 'removed' => 1], $summary['lessons']);
        $this->assertSame(['added' => 0, 'changed' => 0, 'removed' => 0], $summary['questions']);
    }

    public function testACorrectAnswerThatMovedIsOneAnswerKeyChangeForItsQuestion(): void
    {
        $summary = ReleaseRecorder::summary($this->doc(), $this->doc(correctA: false));

        $this->assertSame(1, $summary['answerKeysChanged'], 'two options flipped, one question');
        $this->assertSame(0, $summary['citationsChanged']);
    }

    public function testCitationsThatChangedAreCountedPerElement(): void
    {
        $summary = ReleaseRecorder::summary($this->doc(), $this->doc(citations: ['frg_bbbbbbbbbbbb']));

        // the lesson, its block and its question all point at another passage
        $this->assertSame(3, $summary['citationsChanged']);
    }

    public function testIdenticalDocumentsChangeNothing(): void
    {
        $summary = ReleaseRecorder::summary($this->doc(), $this->doc());

        $this->assertSame(['added' => 0, 'changed' => 0, 'removed' => 0], $summary['counts']);
    }

    public function testALessonMovedToAnotherModuleAndAReorderAreCounted(): void
    {
        $from = $this->doc();
        $to = $from;
        // lesson 2 moves into a new module; nothing else changes
        $moved = array_pop($to['modules'][0]['lessons']);
        $to['modules'][] = ['id' => 'mod_2', 'title' => 'Second', 'summary' => 's', 'lessons' => [$moved]];

        $summary = ReleaseRecorder::summary($from, $to);

        $this->assertSame(1, $summary['lessonsMoved']);
        $this->assertSame(1, $summary['moved']);
        $this->assertSame(['added' => 0, 'changed' => 0, 'removed' => 0], $summary['lessons'], 'a move is not a change');
        $this->assertSame(1, $summary['counts']['added'], 'only the new module is added');

        $reordered = $this->doc();
        $reordered['modules'][0]['lessons'] = array_reverse($reordered['modules'][0]['lessons']);
        $this->assertSame(1, ReleaseRecorder::summary($this->doc(), $reordered)['lessonsMoved'], 'one lesson is outside the common order');
    }

    public function testNothingMovedInAFirstReleaseOrAnIdenticalDocument(): void
    {
        $this->assertSame(0, ReleaseRecorder::summary([], $this->doc())['moved']);
        $this->assertSame(0, ReleaseRecorder::summary($this->doc(), $this->doc())['lessonsMoved']);
    }
}
