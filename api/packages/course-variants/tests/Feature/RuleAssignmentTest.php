<?php

namespace Ulams\CourseVariants\Tests\Feature;

use Ulams\CourseVariants\Contracts\ConditionMatcher;
use Ulams\CourseVariants\Models\Assignment;
use Ulams\CourseVariants\Models\Rule;
use Ulams\CourseVariants\Services\AssignmentService;
use Ulams\CourseVariants\Services\RuleAssigner;
use Ulams\CourseVariants\Tests\Support\FakeMatcher;
use Ulams\CourseVariants\Tests\TestCase;

/** Assignment by rule (ADR 0192): routes learners who already have access; never enrols, never overrides a decision. */
class RuleAssignmentTest extends TestCase
{
    private function rule(array $family, string $variant, string $name, string|array $matches, int $position = 0): Rule
    {
        return Rule::query()->create([
            'group_id' => $family['group']->id, 'course_id' => $family['variants'][$variant]->getKey(), 'name' => $name, 'position' => $position,
            'conditions' => [['attribute' => 'name', 'operator' => 'equals', 'value' => (array) $matches]],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(ConditionMatcher::class, new FakeMatcher());
    }

    public function testAMatchingLearnerGetsTheVariantWhenAccessArrives(): void
    {
        $family = $this->family();
        $this->rule($family, 'expert', 'Sales team', 'sam.sales@example.test');
        $sam = $this->student();
        $sam->forceFill(['email' => 'sam.sales@example.test'])->save();
        $other = $this->student();

        $this->buy($sam, $family['default']);
        $this->buy($other, $family['default']);

        $assignment = Assignment::query()->current()->where('user_id', $sam->getKey())->firstOrFail();
        $this->assertSame($family['variants']['expert']->getKey(), $assignment->course_id);
        $this->assertSame(Assignment::RULE, $assignment->source);
        $this->assertSame(['key' => 'rule', 'params' => ['name' => 'Sales team']], $assignment->reason);
        $this->assertNull(Assignment::query()->where('user_id', $other->getKey())->first(), 'no rule matched the other learner');
    }

    public function testARuleNeverEnrolsSomeoneWithoutAccess(): void
    {
        $family = $this->family();
        $this->rule($family, 'expert', 'Sales team', 'sam.sales@example.test');
        $sam = $this->student();
        $sam->forceFill(['email' => 'sam.sales@example.test'])->save();

        $this->assertSame(0, $this->app->make(RuleAssigner::class)->evaluateAll($family['group']));
        $this->assertNull($this->app->make(AssignmentService::class)->current($sam->getKey(), $family['group']));
    }

    public function testARuleNeverOverridesAChoiceAndTheFirstMatchWins(): void
    {
        $family = $this->family();
        $both = ['sam.sales@example.test', 'chooser@example.test'];
        $this->rule($family, 'expert', 'Second', $both, 2);
        $this->rule($family, 'beginner', 'First', $both, 1);
        $sam = $this->student();
        $sam->forceFill(['email' => 'sam.sales@example.test'])->save();
        $chooser = $this->student();
        $chooser->forceFill(['email' => 'chooser@example.test'])->save();
        $assigner = $this->app->make(RuleAssigner::class);
        $this->buy($chooser, $family['default']);
        $assignments = $this->app->make(AssignmentService::class);

        // the learner chose before the rule ran (rules run when access arrives, so remove the rule outcome and choose)
        Assignment::query()->where('user_id', $chooser->getKey())->delete();
        $assignments->choose($chooser->getKey(), $family['group'], $family['default']->getKey(), Assignment::SELF);
        $this->assertNull($assigner->evaluateUser($chooser, $family['group']), 'a decided learner is skipped');
        $this->assertSame($family['default']->getKey(), $assignments->current($chooser->getKey(), $family['group'])->course_id);

        $this->buy($sam, $family['default']);
        $this->assertSame($family['variants']['beginner']->getKey(), $assignments->current($sam->getKey(), $family['group'])->course_id, 'position order decides');
    }

    public function testTheDailyCommandCatchesLearnersWhoseAttributesChanged(): void
    {
        $family = $this->family();
        $late = $this->student();
        $this->buy($late, $family['default']);
        $this->rule($family, 'expert', 'Sales team', 'late.larry@example.test'); // the rule is added after the learner got access
        $late->forceFill(['email' => 'late.larry@example.test'])->save();

        $this->artisan('course-variants:assign-by-rules')->assertSuccessful();

        $this->assertSame($family['variants']['expert']->getKey(), $this->app->make(AssignmentService::class)->current($late->getKey(), $family['group'])->course_id);
    }

    public function testInactiveRulesAreIgnoredAndPreviewDoesNotAssign(): void
    {
        $family = $this->family();
        $rule = $this->rule($family, 'expert', 'Off', 'pat.preview@example.test');
        $pat = $this->student();
        $pat->forceFill(['email' => 'pat.preview@example.test'])->save();
        $this->buy($pat, $family['default']);
        Assignment::query()->where('user_id', $pat->getKey())->delete();
        $assigner = $this->app->make(RuleAssigner::class);

        $preview = $assigner->preview($family['group'], $rule);
        $this->assertSame(1, $preview['count']);
        $this->assertNull($this->app->make(AssignmentService::class)->current($pat->getKey(), $family['group']));

        $rule->forceFill(['active' => false])->save();
        $this->assertSame(0, $assigner->evaluateAll($family['group']));
    }
}
