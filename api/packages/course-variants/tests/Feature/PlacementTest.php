<?php

namespace Ulams\CourseVariants\Tests\Feature;

use Ulams\CourseVariants\Contracts\PlacementSource;
use Ulams\CourseVariants\Models\PlacementAttempt;
use Ulams\CourseVariants\Models\PlacementItem;
use Ulams\CourseVariants\Services\PlacementService;
use Ulams\CourseVariants\Tests\Support\FakePlacementSource;
use Ulams\CourseVariants\Tests\TestCase;

/**
 * The optional placement check (ADR 0192): cited quiz questions that already exist, scored by code, advisory only.
 */
class PlacementTest extends TestCase
{
    /** @return array{0:array,1:object} */
    private function ready(int $perLevel = 3): array
    {
        $family = $this->family();
        $this->app->instance(PlacementSource::class, new FakePlacementSource($perLevel));
        $family['group']->forceFill(['placement_enabled' => true])->save();
        $this->app->make(PlacementService::class)->rebuild($family['group']->refresh()->load('members'));
        $learner = $this->student();
        $this->buy($learner, $family['default']);

        return [$family, $learner];
    }

    /** @param array<int,string> $rightLevels */
    private function answers(array $rightLevels): array
    {
        $answers = [];
        foreach (PlacementItem::query()->get() as $item) {
            $answers[$item->id] = [in_array($item->level, $rightLevels, true) ? 'a' : 'b'];
        }

        return $answers;
    }

    public function testTheCheckIsBuiltFromCitedScorableQuestionsOnly(): void
    {
        [$family] = $this->ready();

        $items = PlacementItem::query()->where('group_id', $family['group']->id)->get();
        $this->assertCount(9, $items, '3 levels x 3 questions; the uncited and the free-text question are skipped');
        $this->assertEqualsCanonicalizing(['beginner', 'intermediate', 'expert'], $items->pluck('level')->unique()->values()->all());
        $this->assertTrue($items->every(fn ($i) => !empty($i->question['citations'])));
    }

    public function testTheLearnerSeesNoAnswerKeyAndNoLevel(): void
    {
        [$family, $learner] = $this->ready();

        $questions = $this->actingAs($learner, 'api')->getJson("/api/courses/{$family['default']->getKey()}/placement")->assertOk()->json('data.questions');

        $this->assertCount(9, $questions);
        $this->assertSame(['id', 'type', 'stem', 'options'], array_keys($questions[0]));
        $this->assertSame(['id', 'text'], array_keys($questions[0]['options'][0]));
    }

    public function testScoringIsByCodeAndSuggestsTheHighestLevelTheLearnerIsComfortableAt(): void
    {
        [$family, $learner] = $this->ready();

        $all = $this->actingAs($learner, 'api')->postJson("/api/courses/{$family['default']->getKey()}/placement/attempts", ['answers' => $this->answers(['beginner', 'intermediate', 'expert'])])->assertCreated();
        $all->assertJsonPath('data.suggestedCourseId', $family['variants']['expert']->getKey())->assertJsonPath('data.reason.key', 'placement')
            ->assertJsonPath('data.reason.params.level', 'expert')->assertJsonPath('data.reason.params.correct', 3)->assertJsonPath('data.reason.params.total', 3)
            ->assertJsonPath('data.scores.expert.correct', 3);

        $mid = $this->actingAs($learner, 'api')->postJson("/api/courses/{$family['default']->getKey()}/placement/attempts", ['answers' => $this->answers(['beginner', 'intermediate'])])->assertCreated();
        $mid->assertJsonPath('data.suggestedCourseId', $family['default']->getKey())->assertJsonPath('data.reason.params.level', 'intermediate');

        $low = $this->actingAs($learner, 'api')->postJson("/api/courses/{$family['default']->getKey()}/placement/attempts", ['answers' => $this->answers([])])->assertCreated();
        $low->assertJsonPath('data.suggestedCourseId', $family['variants']['beginner']->getKey());
        $this->assertSame(3, PlacementAttempt::query()->count());
        $this->assertArrayNotHasKey('answers', PlacementAttempt::query()->first()->getAttributes());
    }

    public function testTheResultOnlyAdvisesTheLearnerConfirmsOrPicksAnother(): void
    {
        [$family, $learner] = $this->ready();
        $attempt = $this->actingAs($learner, 'api')->postJson("/api/courses/{$family['default']->getKey()}/placement/attempts", ['answers' => $this->answers(['beginner', 'intermediate', 'expert'])])->json('data');
        $this->assertNull($this->actingAs($learner, 'api')->getJson("/api/courses/{$family['default']->getKey()}/variant")->json('data.current'), 'a result changes nothing by itself');

        // confirming a result for another variant than it suggested is refused
        $this->actingAs($learner, 'api')->putJson("/api/courses/{$family['default']->getKey()}/variant", ['course_id' => $family['variants']['beginner']->getKey(), 'source' => 'placement', 'attempt_id' => $attempt['id']])->assertStatus(422);
        $this->actingAs($learner, 'api')->putJson("/api/courses/{$family['default']->getKey()}/variant", ['course_id' => $family['variants']['expert']->getKey(), 'source' => 'placement'])->assertStatus(422);

        $this->actingAs($learner, 'api')->putJson("/api/courses/{$family['default']->getKey()}/variant", ['course_id' => $family['variants']['expert']->getKey(), 'source' => 'placement', 'attempt_id' => $attempt['id']])
            ->assertOk()->assertJsonPath('data.current.source', 'placement')->assertJsonPath('data.current.reason.key', 'placement');
    }

    public function testNoCheckWhenSwitchedOffOrTooThin(): void
    {
        $family = $this->family();
        $this->app->instance(PlacementSource::class, new FakePlacementSource(1));
        $learner = $this->student();
        $this->buy($learner, $family['default']);
        $service = $this->app->make(PlacementService::class);
        $group = $family['group']->load('members');

        $this->assertSame(['items' => 0, 'levels' => []], $service->rebuild($group), '3 questions are too thin: nothing is offered');
        $family['group']->forceFill(['placement_enabled' => true])->save();
        $this->actingAs($learner, 'api')->getJson("/api/courses/{$family['default']->getKey()}/placement")->assertNotFound();

        $this->app->instance(PlacementSource::class, new FakePlacementSource(3));
        $family['group']->forceFill(['placement_enabled' => false])->save();
        $this->app->make(PlacementService::class)->rebuild($group->refresh()->load('members'));
        $this->actingAs($learner, 'api')->getJson("/api/courses/{$family['default']->getKey()}/placement")->assertNotFound();
    }

    public function testOnlyALearnerWithAccessMayTakeIt(): void
    {
        [$family] = $this->ready();
        $stranger = $this->student();

        $this->actingAs($stranger, 'api')->getJson("/api/courses/{$family['default']->getKey()}/placement")->assertForbidden();
        $this->actingAs($stranger, 'api')->postJson("/api/courses/{$family['default']->getKey()}/placement/attempts", ['answers' => []])->assertForbidden();
    }
}
