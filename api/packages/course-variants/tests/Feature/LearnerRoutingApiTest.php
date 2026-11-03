<?php

namespace Ulams\CourseVariants\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\CourseVariants\Models\Assignment;
use Ulams\CourseVariants\Services\Contracts\CourseVariantServiceContract;
use Ulams\CourseVariants\Tests\TestCase;

/** The learner's side of routing (ADR 0192, CV-5): the public picker data, the learner's own variant and the choice. */
class LearnerRoutingApiTest extends TestCase
{
    public function testThePickerListsTheFamilyForAnyoneAndSaysNothingForOtherCourses(): void
    {
        ['default' => $default, 'variants' => $variants] = $this->family();
        $response = $this->getJson("/api/courses/{$variants['expert']->getKey()}/variants")->assertOk(); // no sign-in, any member of the family

        $items = collect($response->json('data.variants'));
        $this->assertSame([$default->getKey(), $variants['beginner']->getKey(), $variants['expert']->getKey()], $items->pluck('courseId')->all());
        $this->assertSame(['Intermediate', 'Beginner', 'Expert'], $items->pluck('label')->all());
        $this->assertTrue($items[0]['isDefault']);
        $this->assertSame($default->getKey(), $response->json('data.defaultCourseId'));
        $this->assertNull($this->getJson('/api/courses/' . $this->publishedCourse('Other')->getKey() . '/variants')->assertOk()->json('data'));
    }

    public function testARetiredOrUnpublishedVariantIsNotOffered(): void
    {
        ['default' => $default, 'variants' => $variants] = $this->family();
        $this->app->make(CourseVariantServiceContract::class)->retire($variants['beginner']->getKey());
        $variants['expert']->forceFill(['status' => CourseStatusEnum::DRAFT])->save();

        $ids = collect($this->getJson("/api/courses/{$default->getKey()}/variants")->assertOk()->json('data.variants'))->pluck('courseId')->all();

        $this->assertSame([$default->getKey()], $ids);
    }

    public function testTheLearnerSeesTheirStateAndChoosesAVariant(): void
    {
        ['default' => $default, 'variants' => $variants] = $this->family();
        $learner = $this->student();
        $this->buy($learner, $default);

        $state = $this->actingAs($learner, 'api')->getJson("/api/courses/{$default->getKey()}/variant")->assertOk()->json('data');
        $this->assertNull($state['current']);
        $this->assertSame($default->getKey(), $state['effectiveCourseId']);
        $this->assertTrue($state['enrolled']);
        $this->assertTrue($state['canChange']);
        $this->assertFalse($state['placement']['available']);
        $this->assertNull($state['hint']);

        $chosen = $this->actingAs($learner, 'api')->putJson("/api/courses/{$default->getKey()}/variant", ['course_id' => $variants['expert']->getKey()])->assertOk();
        $chosen->assertJsonPath('data.current.courseId', $variants['expert']->getKey())->assertJsonPath('data.current.source', 'self')
            ->assertJsonPath('data.effectiveCourseId', $variants['expert']->getKey())->assertJsonPath('data.changed', true)->assertJsonPath('data.current.byOrganisation', false);
        $this->assertTrue(DB::table('course_user')->where(['user_id' => $learner->getKey(), 'course_id' => $variants['expert']->getKey()])->exists());

        // from the variant's own page the same family answers, and choosing the same variant again changes nothing
        $this->actingAs($learner, 'api')->putJson("/api/courses/{$variants['expert']->getKey()}/variant", ['course_id' => $variants['expert']->getKey()])->assertOk()->assertJsonPath('data.changed', false);
        $this->assertSame(1, Assignment::query()->where('user_id', $learner->getKey())->count());
    }

    public function testSwitchingKeepsTheHistoryAndOneCurrentRow(): void
    {
        ['default' => $default, 'variants' => $variants] = $this->family();
        $learner = $this->student();
        $this->buy($learner, $default);
        $this->actingAs($learner, 'api')->putJson("/api/courses/{$default->getKey()}/variant", ['course_id' => $variants['expert']->getKey()])->assertOk();

        $this->actingAs($learner, 'api')->putJson("/api/courses/{$default->getKey()}/variant", ['course_id' => $variants['beginner']->getKey()])->assertOk()
            ->assertJsonPath('data.current.courseId', $variants['beginner']->getKey());

        $rows = Assignment::query()->where('user_id', $learner->getKey())->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertNotNull($rows[0]->superseded_at);
        $this->assertNull($rows[1]->superseded_at);
        $this->assertSame(1, Assignment::query()->current()->where('user_id', $learner->getKey())->count());
        $this->assertTrue(DB::table('course_user')->where(['user_id' => $learner->getKey(), 'course_id' => $variants['expert']->getKey()])->exists(), 'the old course keeps its row and progress');
    }

    public function testAVisitorWithoutAccessCannotChoose(): void
    {
        ['default' => $default, 'variants' => $variants] = $this->family();
        $learner = $this->student();

        $state = $this->actingAs($learner, 'api')->getJson("/api/courses/{$default->getKey()}/variant")->assertOk()->json('data');
        $this->assertFalse($state['enrolled']);
        $this->assertFalse($state['canChange']);
        $this->assertSame('not_enrolled', $state['lockedReason']);
        $this->actingAs($learner, 'api')->putJson("/api/courses/{$default->getKey()}/variant", ['course_id' => $variants['expert']->getKey()])->assertForbidden();
    }

    public function testACourseThatIsNotInTheFamilyIsRefused(): void
    {
        ['default' => $default] = $this->family();
        $learner = $this->student();
        $this->buy($learner, $default);
        $other = $this->publishedCourse('Other');

        $this->actingAs($learner, 'api')->putJson("/api/courses/{$default->getKey()}/variant", ['course_id' => $other->getKey()])->assertStatus(422);
        $this->actingAs($learner, 'api')->getJson("/api/courses/{$other->getKey()}/variant")->assertNotFound();
        $this->actingAs($learner, 'api')->putJson("/api/courses/{$default->getKey()}/variant", [])->assertStatus(422);
    }

    public function testAnAdminAssignmentIsShownAsAssignedByTheOrganisationAndMayBeLocked(): void
    {
        ['group' => $group, 'default' => $default, 'variants' => $variants] = $this->family();
        $learner = $this->student();
        $admin = $this->admin();

        $this->actingAs($admin, 'api')->putJson("/api/admin/course-variant-groups/{$group->id}/assignments", ['course_id' => $variants['expert']->getKey(), 'user_ids' => [$learner->getKey()]])
            ->assertOk()->assertJsonPath('data.assigned', 1);

        $state = $this->actingAs($learner, 'api')->getJson("/api/courses/{$default->getKey()}/variant")->assertOk()->json('data');
        $this->assertSame('admin', $state['current']['source']);
        $this->assertTrue($state['current']['byOrganisation']);
        $this->assertSame(['key' => 'admin'], $state['current']['reason']);
        $this->assertTrue($state['canChange'], 'by default the learner may still change it (Q4)');

        $group->forceFill(['lock_assigned' => true])->save();
        $locked = $this->actingAs($learner, 'api')->getJson("/api/courses/{$default->getKey()}/variant")->assertOk()->json('data');
        $this->assertFalse($locked['canChange']);
        $this->assertSame('locked', $locked['lockedReason']);
        $this->actingAs($learner, 'api')->putJson("/api/courses/{$default->getKey()}/variant", ['course_id' => $variants['beginner']->getKey()])->assertForbidden();

        // the organisation itself may still move the learner
        $this->actingAs($admin, 'api')->putJson("/api/admin/course-variant-groups/{$group->id}/assignments", ['course_id' => $variants['beginner']->getKey(), 'user_ids' => [$learner->getKey()]])->assertOk()->assertJsonPath('data.assigned', 1);
    }

    public function testTheVariantPagesAreOpenButNotListedAndTheDefaultCardNamesThem(): void
    {
        ['default' => $default, 'variants' => $variants] = $this->family();

        $this->getJson("/api/courses/{$variants['expert']->getKey()}")->assertOk()->assertJsonPath('data.title', 'Git basics (Expert)')
            ->assertJsonPath('data.variant_family.defaultCourseId', $default->getKey());
        $listing = $this->getJson('/api/courses?per_page=100')->assertOk()->json('data');
        $ids = collect($listing)->pluck('id')->all();
        $this->assertContains($default->getKey(), $ids);
        $this->assertNotContains($variants['expert']->getKey(), $ids, 'variants stay out of the catalogue');
        $card = collect($listing)->firstWhere('id', $default->getKey());
        $this->assertSame(['Intermediate', 'Beginner', 'Expert'], array_column($card['variant_family']['variants'], 'label'));

        $this->app->make(CourseVariantServiceContract::class)->retire($variants['expert']->getKey());
        $this->getJson("/api/courses/{$variants['expert']->getKey()}")->assertForbidden();
    }
}
