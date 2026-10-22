<?php

namespace Ulams\CourseBuilder\Tests\Unit;

use InvalidArgumentException;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\Variant;
use Ulams\CourseBuilder\Pipeline\BriefService;
use Ulams\CourseBuilder\Pipeline\VariantOutlineService;
use Ulams\CourseBuilder\Tests\TestCase;

/** The audiences of the interview and the plan of one variant (ADR 0192): validation and how the plan becomes an outline. */
class VariantOutlineTest extends TestCase
{
    private const F1 = 'frg_aaaaaaaaaaaa';
    private const F2 = 'frg_bbbbbbbbbbbb';

    public function testLevelsAndRolesBecomeAGridOfVariants(): void
    {
        $this->assertSame([], BriefService::audiences(['single']));
        $this->assertSame(['Beginner', 'Expert'], array_column(BriefService::audiences(['level:beginner', 'level:expert']), 'label'));
        $this->assertSame(['developer', 'Support'], array_column(BriefService::audiences(['developer', 'Support']), 'label'));

        $grid = BriefService::audiences(['level:beginner', 'level:expert', 'developer', 'support']);
        $this->assertSame(['Beginner, developer', 'Beginner, support', 'Expert, developer', 'Expert, support'], array_column($grid, 'label'));
        $this->assertSame(['beginner-developer', 'beginner-support', 'expert-developer', 'expert-support'], array_column($grid, 'key'));
        $this->assertSame(['beginner', 'beginner', 'expert', 'expert'], array_column($grid, 'level'));
        $this->assertSame([null], array_column(BriefService::audiences(['level:expert']), 'role'));
        // chips given twice, in another case, count once
        $this->assertCount(1, BriefService::audiences(['Support', 'support', 'single']));
    }

    public function testTheGridRefusesMoreCellsThanTheCapAndBadInput(): void
    {
        config(['course_builder.variants.max' => 3]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('That is 4 variants and the limit is 3. Remove 1');
        BriefService::audiences(['level:beginner', 'level:expert', 'developer', 'support']);
    }

    public function testALevelIsOneOfTheThree(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BriefService::audiences(['level:wizard']);
    }

    public function testARoleIsPlainText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BriefService::audiences(['<script>alert(1)</script>']);
    }

    public function testAVariantThatIsJustTheBaseLevelIsRefusedOnceTheLevelIsAnswered(): void
    {
        // before the level is answered it cannot clash
        $this->assertCount(1, BriefService::audiences(['level:intermediate'], ['level' => 'intermediate']));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already is for intermediate learners');
        BriefService::audiences(['level:intermediate'], ['level' => 'intermediate', 'decidedBy' => ['level' => 'author']]);
    }

    public function testThePlanMustCoverEveryBaseLessonOnceWithRealFragments(): void
    {
        $known = [self::F1 => true];
        $ok = ['lessons' => [['lessonId' => 'a', 'action' => 'keep', 'reason' => 'Fits.', 'citations' => []], ['lessonId' => 'b', 'action' => 'deepen', 'reason' => 'More depth.', 'citations' => [self::F1]]], 'added' => []];
        $this->assertSame([], VariantOutlineService::validate($ok, ['a', 'b'], $known));

        $errors = implode("\n", VariantOutlineService::validate(['lessons' => [['lessonId' => 'a', 'action' => 'keep', 'reason' => 'x', 'citations' => []]], 'added' => []], ['a', 'b'], $known));
        $this->assertStringContainsString('lesson b has no entry', $errors);

        $errors = implode("\n", VariantOutlineService::validate(['lessons' => [
            ['lessonId' => 'a', 'action' => 'keep', 'reason' => 'x', 'citations' => []], ['lessonId' => 'a', 'action' => 'skip', 'reason' => 'x', 'citations' => []], ['lessonId' => 'zzz', 'action' => 'keep', 'reason' => 'x', 'citations' => []],
        ], 'added' => []], ['a'], $known));
        $this->assertStringContainsString('appears twice', $errors);
        $this->assertStringContainsString('zzz is not a lesson of the base course', $errors);

        $errors = implode("\n", VariantOutlineService::validate(['lessons' => [['lessonId' => 'a', 'action' => 'deepen', 'reason' => 'x', 'citations' => []]], 'added' => []], ['a'], $known));
        $this->assertStringContainsString('must cite at least 1 source fragment', $errors);

        $errors = implode("\n", VariantOutlineService::validate(['lessons' => [['lessonId' => 'a', 'action' => 'deepen', 'reason' => 'x', 'citations' => [self::F2]]], 'added' => []], ['a'], $known));
        $this->assertStringContainsString(self::F2 . ', which is not a fragment of the source', $errors);
    }

    public function testAVariantNeedsAtLeastOneLessonAndAddedLessonsAreGrounded(): void
    {
        $known = [self::F1 => true];
        $skipAll = ['lessons' => [['lessonId' => 'a', 'action' => 'skip', 'reason' => 'x', 'citations' => []]], 'added' => []];
        $this->assertStringContainsString('would have no lessons', implode("\n", VariantOutlineService::validate($skipAll, ['a'], $known)));

        $lesson = ['afterLessonId' => 'nope', 'title' => 'More', 'summary' => 's', 'minutes' => 5, 'objectives' => [['text' => 'Do it', 'citations' => [self::F2]]], 'citations' => [], 'reason' => 'r'];
        $errors = implode("\n", VariantOutlineService::validate(['lessons' => [['lessonId' => 'a', 'action' => 'keep', 'reason' => 'x', 'citations' => []]], 'added' => [$lesson]], ['a'], $known));
        $this->assertStringContainsString('afterLessonId nope is not a lesson of the base course', $errors);
        $this->assertStringContainsString('added/0: must cite at least 1 source fragment', $errors);
        $this->assertStringContainsString('added/0/objectives/0: cites ' . self::F2, $errors);
    }

    public function testThePlanBecomesAnOutlineThatKeepsIdsCopiesKeptLessonsAndClearsTheRest(): void
    {
        $base = $this->baseDocument();
        $variant = new Variant(['key' => 'expert', 'label' => 'Expert', 'level' => 'expert', 'audience_brief' => ['level' => 'expert', 'notes' => 'knows git']]);
        $plan = ['message' => 'm', 'lessons' => [
            ['lessonId' => 'l1', 'action' => 'skip', 'reason' => 'Basics.', 'citations' => []],
            ['lessonId' => 'l2', 'action' => 'condense', 'reason' => 'Shorter.', 'citations' => []],
            ['lessonId' => 'l3', 'action' => 'deepen', 'reason' => 'Deeper.', 'citations' => [self::F2]],
            ['lessonId' => 'l4', 'action' => 'keep', 'reason' => 'Fits.', 'citations' => []],
        ], 'added' => [
            ['afterLessonId' => 'l2', 'title' => 'Rebasing', 'summary' => 's', 'minutes' => 7, 'objectives' => [['text' => 'Rebase a branch', 'citations' => [self::F1]]], 'citations' => [self::F1], 'reason' => 'asked'],
            ['afterLessonId' => '', 'title' => 'At the end', 'summary' => '', 'minutes' => 3, 'objectives' => [['text' => 'Wrap up', 'citations' => [self::F1]]], 'citations' => [self::F1], 'reason' => 'asked'],
        ]];

        [$doc, $stored] = app(VariantOutlineService::class)->compose($base, $variant, $plan);

        $this->assertSame('Course (Expert)', $doc['course']['title']);
        $this->assertSame('Expert: knows git', $doc['course']['audience']);
        $this->assertSame($base['course']['id'], $doc['course']['id']);
        $lessons = collect(iterator_to_array(Blueprint::lessons($doc), false))->map(fn ($i) => $i['lesson'])->keyBy('id');
        $this->assertNull($lessons['l1'] ?? null, 'a skipped lesson is gone');
        // condense: same id and objectives, shorter, text cleared so it is written again
        $this->assertSame($base['modules'][0]['lessons'][1]['objectives'], $lessons['l2']['objectives']);
        $this->assertSame(6, $lessons['l2']['minutes']);
        $this->assertSame([], $lessons['l2']['blocks']);
        $this->assertNull($lessons['l2']['quiz']);
        $this->assertArrayNotHasKey('selfChecks', $lessons['l2']);
        // deepen: longer and cites the extra fragments
        $this->assertSame(14, $lessons['l3']['minutes']);
        $this->assertContains(self::F2, $lessons['l3']['citations']);
        // keep: copied as it is, with its text and quiz
        $this->assertSame($base['modules'][1]['lessons'][0], $lessons['l4']);
        // added lessons get new ids; one follows l2, one is at the end of the last module
        $added = array_values(array_filter($lessons->all(), fn ($l) => !isset($base['modules'][0]['lessons'][0]) || !in_array($l['id'], ['l1', 'l2', 'l3', 'l4'], true)));
        $this->assertSame(['Rebasing', 'At the end'], array_column($added, 'title'));
        $this->assertTrue(Blueprint::isId($added[0]['id']));
        $this->assertSame('Rebasing', $doc['modules'][0]['lessons'][1]['title'] ?? null);
        $this->assertSame('At the end', end($doc['modules'])['lessons'][array_key_last(end($doc['modules'])['lessons'])]['title']);
        // the final test is written again because lessons changed
        $this->assertNull($doc['finalTest']);
        // the plan kept on the variant
        $this->assertSame(['skip', 'condense', 'deepen', 'keep'], array_values(array_column($stored['lessons'], 'action')));
        $this->assertCount(2, $stored['added']);
    }

    public function testAVariantThatChangesNothingKeepsTheFinalTest(): void
    {
        $base = $this->baseDocument();
        $variant = new Variant(['key' => 'dev', 'label' => 'Developer', 'level' => null, 'role' => 'developer', 'audience_brief' => []]);
        $plan = ['message' => 'm', 'lessons' => array_map(fn ($id) => ['lessonId' => $id, 'action' => 'keep', 'reason' => 'ok', 'citations' => []], ['l1', 'l2', 'l3', 'l4']), 'added' => []];
        [$doc] = app(VariantOutlineService::class)->compose($base, $variant, $plan);
        $this->assertSame($base['finalTest'], $doc['finalTest']);
        $this->assertSame(array_column(array_column($base['modules'], 'lessons'), 0), array_column(array_column($doc['modules'], 'lessons'), 0));
    }

    /** @return array<string,mixed> two modules, four generated lessons, a final test */
    private function baseDocument(): array
    {
        $lesson = fn (string $id, int $minutes) => ['id' => $id, 'title' => "Lesson {$id}", 'minutes' => $minutes, 'objectives' => [['id' => "o{$id}", 'text' => "Do {$id}", 'citations' => [self::F1]]], 'citations' => [self::F1],
            'contentType' => 'richtext', 'status' => 'generated', 'flags' => [],
            'blocks' => [['id' => "b{$id}", 'kind' => 'paragraph', 'markdown' => "Text {$id}", 'citations' => [self::F1], 'objectiveIds' => ["o{$id}"]]],
            'quiz' => ['id' => "q{$id}", 'questions' => []], 'selfChecks' => []];
        $doc = Blueprint::empty('Course', 'en');
        $doc['course']['id'] = 'course1';
        $doc['modules'] = [['id' => 'm1', 'title' => 'One', 'lessons' => [$lesson('l1', 10), $lesson('l2', 10), $lesson('l3', 10)]], ['id' => 'm2', 'title' => 'Two', 'lessons' => [$lesson('l4', 10)]]];
        $doc['finalTest'] = ['id' => 'ft', 'passScore' => 70, 'questions' => []];

        return $doc;
    }
}
