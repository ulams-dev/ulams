<?php

namespace Ulams\CourseVariants\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\Courses\Models\CourseGroupPivot;
use Ulams\Courses\Models\Group;
use Ulams\CourseVariants\Models\Assignment;
use Ulams\CourseVariants\Services\AssignmentService;
use Ulams\CourseVariants\Services\FamilyAccess;
use Ulams\CourseVariants\Exceptions\VariantException;
use Ulams\CourseVariants\Tests\TestCase;
use Ulams\Entitlements\Models\Entitlement;
use Ulams\Entitlements\Services\EntitlementService;

/**
 * One entitlement covers the whole family (ADR 0192, ADR 0130): the LMS decides, the order only grants one course. The
 * variants a learner uses get a derived `family` entitlement that ends with the entitlements it derives from.
 */
class FamilyAccessTest extends TestCase
{
    private function assignments(): AssignmentService
    {
        return $this->app->make(AssignmentService::class);
    }

    private function holds(int $userId, int $courseId): bool
    {
        return DB::table('course_user')->where('user_id', $userId)->where('course_id', $courseId)->exists();
    }

    public function testAnEntitlementForTheDefaultCourseOpensEveryVariantAfterTheLearnerPicksOne(): void
    {
        ['group' => $group, 'default' => $default, 'variants' => $variants] = $this->family();
        $learner = $this->student();
        $this->buy($learner, $default);

        $this->assertTrue($this->holds($learner->getKey(), $default->getKey()));
        $this->assertFalse($this->holds($learner->getKey(), $variants['expert']->getKey()), 'nothing is opened until the learner chooses');

        $this->assignments()->choose($learner->getKey(), $group, $variants['expert']->getKey(), Assignment::SELF);

        $this->assertTrue($this->holds($learner->getKey(), $variants['expert']->getKey()));
        $derived = Entitlement::query()->where('user_id', $learner->getKey())->where('source_type', Entitlement::SOURCE_FAMILY)->get();
        $this->assertCount(1, $derived);
        $this->assertSame($variants['expert']->getKey(), $derived[0]->subject_id);
        $this->assertSame($group->id, $derived[0]->source_ref['group_id']);
        $this->assertTrue($this->holds($learner->getKey(), $default->getKey()), 'the course they bought stays open, with its progress');
    }

    public function testRevokingTheOrderEndsTheDerivedAccessToo(): void
    {
        ['group' => $group, 'default' => $default, 'variants' => $variants] = $this->family();
        $learner = $this->student();
        $order = $this->buy($learner, $default);
        $this->assignments()->choose($learner->getKey(), $group, $variants['beginner']->getKey(), Assignment::SELF);
        $this->assertTrue($this->holds($learner->getKey(), $variants['beginner']->getKey()));

        $this->app->make(EntitlementService::class)->revoke($order->source_key, 'refund');

        $this->assertFalse($this->holds($learner->getKey(), $default->getKey()));
        $this->assertFalse($this->holds($learner->getKey(), $variants['beginner']->getKey()), 'a refund removes the whole family');
        $this->assertSame(Entitlement::STATUS_REVOKED, Entitlement::query()->where('source_type', Entitlement::SOURCE_FAMILY)->firstOrFail()->status);
        $this->assertFalse($this->app->make(FamilyAccess::class)->covers($learner->getKey(), $group));
    }

    public function testTheDerivedEntitlementFollowsTheEndDateOfTheOrder(): void
    {
        ['group' => $group, 'default' => $default, 'variants' => $variants] = $this->family();
        $learner = $this->student();
        $this->buy($learner, $default, now()->addDays(30)->startOfSecond());
        $this->assignments()->choose($learner->getKey(), $group, $variants['expert']->getKey(), Assignment::SELF);
        $derived = fn () => Entitlement::query()->where('source_type', Entitlement::SOURCE_FAMILY)->firstOrFail();
        $this->assertSame(now()->addDays(30)->startOfSecond()->getTimestamp(), $derived()->valid_until->getTimestamp());

        // a renewal: a second order for the same course with a later end moves the derived end
        $this->buy($learner, $default, now()->addDays(90)->startOfSecond(), 'order:renewal');

        $this->assertSame(now()->addDays(90)->startOfSecond()->getTimestamp(), $derived()->valid_until->getTimestamp());
    }

    public function testALearnerWithoutAnEntitlementCannotChooseButAnAdminAssignmentGrantsOne(): void
    {
        ['group' => $group, 'default' => $default, 'variants' => $variants] = $this->family();
        $learner = $this->student();

        try {
            $this->assignments()->choose($learner->getKey(), $group, $variants['expert']->getKey(), Assignment::SELF);
            $this->fail('Choosing a variant needs access to the family.');
        } catch (VariantException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertFalse($this->holds($learner->getKey(), $variants['expert']->getKey()));

        $result = $this->assignments()->choose($learner->getKey(), $group, $variants['expert']->getKey(), Assignment::ADMIN, ['key' => 'admin'], null);

        $this->assertTrue($result['changed']);
        $this->assertTrue($this->holds($learner->getKey(), $variants['expert']->getKey()));
        $this->assertTrue($this->app->make(FamilyAccess::class)->covers($learner->getKey(), $group), 'the admin grant covers the family');
        $this->assertFalse($this->holds($learner->getKey(), $default->getKey()), 'only the assigned variant is opened');
    }

    public function testARuleNeverEnrolsAnyone(): void
    {
        ['group' => $group, 'variants' => $variants] = $this->family();
        $learner = $this->student();

        $this->expectException(VariantException::class);
        $this->assignments()->choose($learner->getKey(), $group, $variants['expert']->getKey(), Assignment::RULE, ['key' => 'rule'], null, 1);
    }

    public function testAccessThroughAGroupCoversTheFamily(): void
    {
        ['group' => $group, 'default' => $default, 'variants' => $variants] = $this->family();
        $learner = $this->student();
        $userGroup = Group::factory()->create();
        DB::table('group_user')->insert(['group_id' => $userGroup->getKey(), 'user_id' => $learner->getKey()]);
        CourseGroupPivot::query()->insert(['course_id' => $default->getKey(), 'group_id' => $userGroup->getKey()]);

        $this->assertTrue($this->app->make(FamilyAccess::class)->covers($learner->getKey(), $group));
        $this->assignments()->choose($learner->getKey(), $group, $variants['beginner']->getKey(), Assignment::SELF);
        $this->assertTrue($this->holds($learner->getKey(), $variants['beginner']->getKey()));
        $this->assertNull(Entitlement::query()->where('source_type', Entitlement::SOURCE_FAMILY)->first()?->valid_until, 'a group has no end');
    }
}
