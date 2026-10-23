<?php

namespace Ulams\CourseVariants\Tests\Feature;

use Ulams\CourseVariants\Contracts\FitSignals;
use Ulams\CourseVariants\Models\Assignment;
use Ulams\CourseVariants\Models\Hint;
use Ulams\CourseVariants\Services\AssignmentService;
use Ulams\CourseVariants\Tests\Support\FakeFit;
use Ulams\CourseVariants\Tests\TestCase;

/** The adaptive hint (ADR 0192): a dismissible suggestion with reasons; at most one a week; dismissing stops them; never a silent switch. */
class HintTest extends TestCase
{
    private function setUpLearner(?string $direction): array
    {
        $family = $this->family();
        $fit = new FakeFit($direction);
        $this->app->instance(FitSignals::class, $fit);
        $learner = $this->student();
        $this->buy($learner, $family['default']);

        return [$family, $learner, $fit];
    }

    private function state(array $family, $learner): array
    {
        return $this->actingAs($learner, 'api')->getJson("/api/courses/{$family['default']->getKey()}/variant")->assertOk()->json('data');
    }

    public function testATooEasyVariantHintsAtTheNextLevelUpWithTheReasons(): void
    {
        [$family, $learner] = $this->setUpLearner('harder');

        $hint = $this->state($family, $learner)['hint'];

        $this->assertSame('harder', $hint['direction']);
        $this->assertSame($family['default']->getKey(), $hint['courseId']);
        $this->assertSame($family['variants']['expert']->getKey(), $hint['suggestedCourseId']);
        $this->assertSame('variant.too_easy', $hint['reasons'][0]['messageKey']);
        $this->assertSame($family['default']->getKey(), $this->state($family, $learner)['effectiveCourseId'], 'nothing switched by itself');
    }

    public function testAcceptingTheHintSwitchesAndRecordsWhy(): void
    {
        [$family, $learner] = $this->setUpLearner('easier');
        $hint = $this->state($family, $learner)['hint'];
        $this->assertSame($family['variants']['beginner']->getKey(), $hint['suggestedCourseId']);

        $response = $this->actingAs($learner, 'api')->putJson("/api/courses/{$family['default']->getKey()}/variant", ['course_id' => $hint['suggestedCourseId'], 'source' => 'hint', 'hint_id' => $hint['id']])->assertOk();

        $response->assertJsonPath('data.current.source', 'hint')->assertJsonPath('data.current.reason.key', 'hint')->assertJsonPath('data.current.reason.params.direction', 'easier');
        $this->assertSame(Hint::ACCEPTED, Hint::query()->findOrFail($hint['id'])->status);
        $this->assertNull($response->json('data.hint'), 'no second hint the same week');
    }

    public function testDismissingStopsHintsForThatCourse(): void
    {
        [$family, $learner, $fit] = $this->setUpLearner('harder');
        $hint = $this->state($family, $learner)['hint'];

        $this->actingAs($learner, 'api')->postJson("/api/courses/{$family['default']->getKey()}/variant/hints/{$hint['id']}/dismiss")->assertOk()->assertJsonPath('data.hint', null);
        $this->travel(30)->days();

        $this->assertNull($this->state($family, $learner)['hint'], 'dismissing stops hints for good, not for a week');
        $this->assertSame(1, Hint::query()->count());
    }

    public function testAtMostOneHintAWeek(): void
    {
        [$family, $learner] = $this->setUpLearner('harder');
        $first = $this->state($family, $learner)['hint'];
        $this->assertSame($first['id'], $this->state($family, $learner)['hint']['id'], 'an open hint stays');
        Hint::query()->whereKey($first['id'])->update(['status' => Hint::ACCEPTED]);

        $this->assertNull($this->state($family, $learner)['hint'], 'a hint was made less than a week ago');
        $this->travel(8)->days();
        $this->assertNotNull($this->state($family, $learner)['hint']);
        $this->assertSame(2, Hint::query()->count());
    }

    public function testNoHintWithoutASignalOrAHarderVariantOrWhenSwitchedOffOrLocked(): void
    {
        [$family, $learner, $fit] = $this->setUpLearner(null);
        $this->assertNull($this->state($family, $learner)['hint']);

        $fit->direction = 'harder';
        $family['group']->forceFill(['hints_enabled' => false])->save();
        $this->assertNull($this->state($family, $learner)['hint'], 'an admin switched hints off');
        $family['group']->forceFill(['hints_enabled' => true, 'lock_assigned' => true])->save();
        $this->app->make(AssignmentService::class)->choose($learner->getKey(), $family['group']->refresh()->load('members'), $family['default']->getKey(), Assignment::ADMIN, ['key' => 'admin']);
        $this->assertNull($this->state($family, $learner)['hint'], 'the learner cannot switch, so no hint to');

        // already in the hardest variant: nothing harder to suggest
        $family['group']->forceFill(['lock_assigned' => false])->save();
        $this->app->make(AssignmentService::class)->choose($learner->getKey(), $family['group']->refresh()->load('members'), $family['variants']['expert']->getKey(), Assignment::SELF);
        $this->assertNull($this->state($family, $learner)['hint']);
    }

    public function testAHintForAVariantTheLearnerLeftExpiresAndDoesNotStopLaterHints(): void
    {
        [$family, $learner] = $this->setUpLearner('harder');
        $hint = $this->state($family, $learner)['hint'];
        $this->app->make(AssignmentService::class)->choose($learner->getKey(), $family['group']->load('members'), $family['variants']['beginner']->getKey(), Assignment::SELF);

        $this->assertNull($this->state($family, $learner)['hint'], 'it was about the variant the learner has left');
        $this->assertSame(Hint::EXPIRED, Hint::query()->findOrFail($hint['id'])->status);
        $this->travel(8)->days();
        $this->assertNotNull($this->state($family, $learner)['hint'], 'an expired hint is not a dismissal');
    }

    public function testAHintOfAnotherLearnerCannotBeUsed(): void
    {
        [$family, $learner] = $this->setUpLearner('harder');
        $hint = $this->state($family, $learner)['hint'];
        $other = $this->student();
        $this->buy($other, $family['default']);

        $this->actingAs($other, 'api')->putJson("/api/courses/{$family['default']->getKey()}/variant", ['course_id' => $hint['suggestedCourseId'], 'source' => 'hint', 'hint_id' => $hint['id']])->assertStatus(422);
        $this->actingAs($other, 'api')->postJson("/api/courses/{$family['default']->getKey()}/variant/hints/{$hint['id']}/dismiss")->assertNotFound();
    }
}
