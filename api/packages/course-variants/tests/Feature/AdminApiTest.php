<?php

namespace Ulams\CourseVariants\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\Core\Tests\Concerns\ForeignTenantToken;
use Ulams\Courses\Models\Group;
use Ulams\CourseVariants\Contracts\ConditionMatcher;
use Ulams\CourseVariants\Contracts\PlacementSource;
use Ulams\CourseVariants\Models\Assignment;
use Ulams\CourseVariants\Models\Rule;
use Ulams\CourseVariants\Tests\Support\FakeMatcher;
use Ulams\CourseVariants\Tests\Support\FakePlacementSource;
use Ulams\CourseVariants\Tests\TestCase;

/** The admin endpoints (ADR 0192): settings of a family, who is where, assignments, rules and the placement questions. */
class AdminApiTest extends TestCase
{
    use ForeignTenantToken;

    private function url(array $family, string $path = ''): string
    {
        return "/api/admin/course-variant-groups/{$family['group']->id}{$path}";
    }

    public function testOnlyAdminsWithThePermissionMayUseThem(): void
    {
        $family = $this->family();
        $student = $this->student();

        $routes = [['GET', ''], ['PUT', ''], ['GET', '/assignments'], ['PUT', '/assignments'], ['GET', '/rules'], ['POST', '/rules'], ['GET', '/placement'], ['POST', '/placement/rebuild']];
        foreach ($routes as [$method, $path]) {
            $this->sendAs(null, $method, $this->url($family, $path))->assertUnauthorized();
        }
        foreach ($routes as [$method, $path]) {
            $this->actingAs($student, 'api')->json($method, $this->url($family, $path), [])->assertForbidden();
        }
        $this->actingAs($student, 'api')->getJson('/api/admin/course-variant-groups')->assertForbidden();
    }

    public function testListAndShowDescribeTheFamily(): void
    {
        $family = $this->family();
        $admin = $this->admin();

        $list = $this->actingAs($admin, 'api')->getJson('/api/admin/course-variant-groups?course_id=' . $family['variants']['expert']->getKey())->assertOk();
        $list->assertJsonPath('data.total', 1)->assertJsonPath('data.groups.0.id', $family['group']->id)->assertJsonPath('data.groups.0.variants', 3)->assertJsonPath('data.groups.0.defaultTitle', 'Git basics');
        $this->actingAs($admin, 'api')->getJson('/api/admin/course-variant-groups?course_id=999999')->assertOk()->assertJsonPath('data.total', 0);

        $show = $this->actingAs($admin, 'api')->getJson($this->url($family))->assertOk()->json('data');
        $this->assertSame([$family['default']->getKey(), $family['variants']['beginner']->getKey(), $family['variants']['expert']->getKey()], array_column($show['courses'], 'courseId'));
        $this->assertTrue($show['courses'][0]['isDefault']);
        $this->assertFalse($show['lockAssigned']);
        $this->assertSame([], $show['learners']);
        $this->actingAs($admin, 'api')->getJson('/api/admin/course-variant-groups/01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertNotFound();
    }

    public function testSettingsAreValidated(): void
    {
        $family = $this->family();
        $admin = $this->admin();

        $this->actingAs($admin, 'api')->putJson($this->url($family), ['lock_assigned' => true, 'placement_enabled' => true, 'pinned_course_id' => $family['variants']['expert']->getKey()])
            ->assertOk()->assertJsonPath('data.lockAssigned', true)->assertJsonPath('data.pinnedCourseId', $family['variants']['expert']->getKey())->assertJsonPath('data.placementEnabled', true);
        $this->actingAs($admin, 'api')->putJson($this->url($family), ['pinned_course_id' => $this->publishedCourse('Elsewhere')->getKey()])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson($this->url($family), ['pinned_course_id' => null])->assertOk()->assertJsonPath('data.pinnedCourseId', null);
        $this->actingAs($admin, 'api')->putJson($this->url($family), ['lock_assigned' => 'maybe'])->assertStatus(422);
    }

    public function testAssignLearnersAndWholeUserGroups(): void
    {
        $family = $this->family();
        $admin = $this->admin();
        $one = $this->student();
        $two = $this->student();
        $three = $this->student();
        $team = Group::factory()->create();
        DB::table('group_user')->insert([['group_id' => $team->getKey(), 'user_id' => $two->getKey()], ['group_id' => $team->getKey(), 'user_id' => $three->getKey()]]);

        $this->actingAs($admin, 'api')->putJson($this->url($family, '/assignments'), ['course_id' => $family['variants']['expert']->getKey(), 'user_ids' => [$one->getKey()], 'group_ids' => [$team->getKey()]])
            ->assertOk()->assertJsonPath('data.matched', 3)->assertJsonPath('data.assigned', 3);
        $this->actingAs($admin, 'api')->putJson($this->url($family, '/assignments'), ['course_id' => $family['variants']['expert']->getKey(), 'user_ids' => [$one->getKey()]])->assertOk()->assertJsonPath('data.assigned', 0);
        $this->actingAs($admin, 'api')->putJson($this->url($family, '/assignments'), ['course_id' => $this->publishedCourse('Elsewhere')->getKey(), 'user_ids' => [$one->getKey()]])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson($this->url($family, '/assignments'), ['course_id' => $family['default']->getKey()])->assertStatus(422);

        $list = $this->actingAs($admin, 'api')->getJson($this->url($family, '/assignments'))->assertOk();
        $list->assertJsonPath('data.total', 3)->assertJsonPath('data.summary.0.learners', 3)->assertJsonPath('data.summary.0.source', 'admin');
        $this->assertSame(1, count($this->actingAs($admin, 'api')->getJson($this->url($family, '/assignments?user_id=' . $one->getKey()))->json('data.assignments')));
        $this->assertSame(3, Assignment::query()->current()->count());
    }

    public function testRulesCanBeManagedPreviewedAndRun(): void
    {
        $this->app->instance(ConditionMatcher::class, new FakeMatcher());
        $family = $this->family();
        $admin = $this->admin();
        $learner = $this->student();
        $this->buy($learner, $family['default']);
        Assignment::query()->delete(); // the rule below did not exist when the learner got access

        $created = $this->actingAs($admin, 'api')->postJson($this->url($family, '/rules'), [
            'name' => 'Experts', 'course_id' => $family['variants']['expert']->getKey(),
            'conditions' => [['attribute' => 'email', 'operator' => 'equals', 'value' => [$learner->email]]],
        ])->assertCreated();
        $rule = $created->json('data.id');
        $this->assertSame($family['variants']['expert']->getKey(), $created->json('data.courseId'));

        $this->actingAs($admin, 'api')->postJson($this->url($family, "/rules/{$rule}/evaluate"))->assertOk()->assertJsonPath('data.count', 1)->assertJsonPath('data.sample.0.id', $learner->getKey());
        $this->assertSame(0, Assignment::query()->count(), 'a preview changes nothing');
        $this->actingAs($admin, 'api')->postJson($this->url($family, "/rules/{$rule}/evaluate"), ['dry_run' => false])->assertOk()->assertJsonPath('data.assigned', 1);
        $this->assertSame(Assignment::RULE, Assignment::query()->current()->firstOrFail()->source);

        $this->actingAs($admin, 'api')->putJson($this->url($family, "/rules/{$rule}"), ['active' => false, 'name' => 'Experts (paused)'])->assertOk()->assertJsonPath('data.active', false);
        $this->assertCount(1, $this->actingAs($admin, 'api')->getJson($this->url($family, '/rules'))->json('data'));
        $this->actingAs($admin, 'api')->postJson($this->url($family, '/rules'), ['name' => 'Bad', 'course_id' => $this->publishedCourse('Elsewhere')->getKey(), 'conditions' => [['attribute' => 'email', 'operator' => 'equals', 'value' => ['x']]]])->assertStatus(422);
        $this->actingAs($admin, 'api')->postJson($this->url($family, '/rules'), ['name' => 'Bad', 'course_id' => $family['default']->getKey(), 'conditions' => [['attribute' => 'email', 'operator' => 'matches']]])->assertStatus(422);
        $this->actingAs($admin, 'api')->deleteJson($this->url($family, "/rules/{$rule}"))->assertOk();
        $this->assertSame(0, Rule::query()->count());
        $this->assertSame(1, Assignment::query()->current()->count(), 'learners already assigned stay');
    }

    public function testARuleOfAnotherFamilyIsNotReachable(): void
    {
        $family = $this->family();
        $other = $this->family();
        $admin = $this->admin();
        $rule = Rule::query()->create(['group_id' => $other['group']->id, 'course_id' => $other['default']->getKey(), 'name' => 'Theirs', 'conditions' => [['attribute' => 'email', 'operator' => 'exists']]]);

        $this->actingAs($admin, 'api')->putJson($this->url($family, "/rules/{$rule->id}"), ['name' => 'Mine now'])->assertNotFound();
        $this->actingAs($admin, 'api')->deleteJson($this->url($family, "/rules/{$rule->id}"))->assertNotFound();
        $this->actingAs($admin, 'api')->postJson($this->url($family, "/rules/{$rule->id}/evaluate"))->assertNotFound();
    }

    public function testThePlacementQuestionsAreRebuiltAndShownWithTheirKey(): void
    {
        $this->app->instance(PlacementSource::class, new FakePlacementSource(3));
        $family = $this->family();
        $admin = $this->admin();

        $this->actingAs($admin, 'api')->postJson($this->url($family, '/placement/rebuild'))->assertOk()->assertJsonPath('data.items', 9)->assertJsonPath('data.available', false); // still switched off
        $this->actingAs($admin, 'api')->putJson($this->url($family), ['placement_enabled' => true])->assertOk();

        $placement = $this->actingAs($admin, 'api')->getJson($this->url($family, '/placement'))->assertOk()->json('data');
        $this->assertTrue($placement['enabled']);
        $this->assertTrue($placement['available']);
        $this->assertSame(['beginner', 'intermediate', 'expert'], $placement['levels']);
        $this->assertCount(9, $placement['items']);
        $this->assertTrue($placement['items'][0]['question']['options'][0]['correct']);
        $this->assertNotEmpty($placement['items'][0]['question']['citations']);
    }
}
