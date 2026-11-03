<?php

namespace Ulams\Entitlements\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\CourseAccess\Models\Course;
use Ulams\CourseAccess\Services\CourseAccessService;
use Ulams\Courses\Models\User;
use Ulams\Entitlements\Models\Entitlement;
use Ulams\Entitlements\Models\ParityReport;
use Ulams\Entitlements\Services\EntitlementService;
use Ulams\Entitlements\Support\AccessParity;
use Ulams\Entitlements\Tests\TestCase;

class AccessParityTest extends TestCase
{
    private function legacy(User $user, Course $course, $end = null): void
    {
        (new CourseAccessService())->addAccessForUsers($course, [$user->getKey() => ['end_date' => $end]]);
    }

    public function testAnEmptyTenantHasNothingToCompare(): void
    {
        $result = app(AccessParity::class)->check();

        $this->assertTrue($result->passed());
        $this->assertSame(0, $result->pairs);
    }

    public function testEntitlementsAndTheirProjectionAgree(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        app(EntitlementService::class)->grantCourse($user->getKey(), $course->getKey(), 'order_item', 'a', [], now()->addDays(9));

        $result = app(AccessParity::class)->check();

        $this->assertSame([1, 0], [$result->pairs, $result->differences]);
    }

    public function testAnAccessRowNobodyExplainsIsReported(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->legacy($user, $course);

        $result = app(AccessParity::class)->check();

        $this->assertSame(['missing_entitlement' => 1], $result->byKind);
        $this->assertSame(['user_id' => $user->getKey(), 'course_id' => $course->getKey(), 'kind' => 'missing_entitlement', 'expected_end' => null, 'actual_end' => null], $result->sample[0]);
    }

    public function testAnEntitlementWithoutAccessIsReported(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        app(EntitlementService::class)->grantCourse($user->getKey(), $course->getKey(), 'order_item', 'a');
        DB::table('course_user')->delete(); // a code path removed the row behind the entitlements' back

        $this->assertSame(['unprojected_entitlement' => 1], app(AccessParity::class)->check()->byKind);
    }

    public function testADifferentEndDateIsReported(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        app(EntitlementService::class)->grantCourse($user->getKey(), $course->getKey(), 'order_item', 'a', [], now()->addDays(9));
        DB::table('course_user')->update(['end_date' => now()->addDays(20)]);

        $this->assertSame(['end_date_mismatch' => 1], app(AccessParity::class)->check()->byKind);
    }

    public function testALapsedRowWithoutAnEntitlementIsNotADifference(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->legacy($user, $course, now()->subDays(4));

        $this->assertTrue(app(AccessParity::class)->check()->passed());
    }

    public function testRevokedAndExpiredEntitlementsGiveNoAccessOnEitherSide(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $service = app(EntitlementService::class);
        $service->grantCourse($user->getKey(), $course->getKey(), 'order_item', 'a');
        $service->revoke('a', 'refund');

        $this->assertTrue(app(AccessParity::class)->check()->passed());
    }

    public function testGroupAccessIsNotCompared(): void
    {
        $course = Course::factory()->create();
        $group = \Ulams\Auth\Models\Group::factory()->create();
        (new CourseAccessService())->addAccessForGroups($course, [$group->getKey()]);

        $this->assertSame(0, app(AccessParity::class)->check()->pairs);
    }

    public function testTheSampleIsCappedButTheCountIsNot(): void
    {
        $course = Course::factory()->create();
        foreach (User::factory()->count(3)->create() as $user) {
            $this->legacy($user, $course);
        }

        $result = app(AccessParity::class)->check(2);

        $this->assertSame(3, $result->differences);
        $this->assertCount(2, $result->sample);
    }

    public function testTheSnapshotChangesWhenAccessChanges(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $parity = app(AccessParity::class);
        $empty = $parity->snapshot();

        $this->legacy($user, $course);
        $with = $parity->snapshot();
        $this->legacy($user, $course, now()->addDay());

        $this->assertNotSame($empty['course_user'], $with['course_user']);
        $this->assertSame($with['course_user'], hash('sha256', "{$user->getKey()}|{$course->getKey()}|\n"));
        $this->assertNotSame($with['course_user'], $parity->snapshot()['course_user']);
    }

    public function testTheCommandStoresAPassingReport(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        app(EntitlementService::class)->grantCourse($user->getKey(), $course->getKey(), 'order_item', 'a');

        $this->artisan('ulams:entitlements:parity')->expectsOutputToContain('1 pair(s) checked, 0 difference(s)')->assertSuccessful();

        $report = ParityReport::query()->firstOrFail();
        $this->assertSame(['check', 'passed', 1, 0], [$report->kind, $report->status, $report->pairs_checked, $report->differences]);
    }

    public function testTheCommandFailsAndKeepsTheEvidenceWhenAccessDiffers(): void
    {
        $this->legacy(User::factory()->create(), Course::factory()->create());

        $this->artisan('ulams:entitlements:parity')->expectsOutputToContain('missing_entitlement: 1')->assertFailed();

        $report = ParityReport::query()->firstOrFail();
        $this->assertSame(['failed', 1], [$report->status, $report->differences]);
        $this->assertSame('missing_entitlement', $report->details['sample'][0]['kind']);
        $this->assertSame(0, Entitlement::query()->count(), 'the check never repairs');
    }

    public function testLatestPrintsTheStoredReport(): void
    {
        $this->artisan('ulams:entitlements:parity --latest')->assertFailed();

        $this->artisan('ulams:entitlements:parity')->assertSuccessful();
        $this->artisan('ulams:entitlements:parity --latest')->expectsOutputToContain('0 difference(s)')->assertSuccessful();
    }
}
