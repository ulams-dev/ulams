<?php

namespace Ulams\Entitlements\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Ulams\CourseAccess\Models\Course;
use Ulams\CourseAccess\Services\Contracts\CourseAccessServiceContract;
use Ulams\Courses\Events\CourseAssigned;
use Ulams\Courses\Events\CourseUnassigned;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Courses\Models\User;
use Ulams\Entitlements\Models\Entitlement;
use Ulams\Entitlements\Services\EntitlementService;
use Ulams\Entitlements\Tests\TestCase;

class EntitlementServiceTest extends TestCase
{
    private EntitlementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EntitlementService::class);
    }

    private function pivot(User $user, Course $course): ?CourseUserPivot
    {
        return CourseUserPivot::query()->where('user_id', $user->getKey())->where('course_id', $course->getKey())->first();
    }

    private function grant(User $user, Course $course, string $key, $until = null, string $type = 'order_item'): Entitlement
    {
        return $this->service->grantCourse($user->getKey(), $course->getKey(), $type, $key, ['n' => 1], $until);
    }

    public function testGrantCreatesTheEntitlementAndTheCourseUserProjection(): void
    {
        Event::fake([CourseAssigned::class]);
        $user = User::factory()->create();
        $course = Course::factory()->create();

        $entitlement = $this->grant($user, $course, 'k1');

        $this->assertSame(26, strlen($entitlement->id), 'ULID');
        $this->assertSame(['active', 'course', 'k1'], [$entitlement->status, $entitlement->subject_type, $entitlement->source_key]);
        $this->assertNotNull($this->pivot($user, $course));
        $this->assertNull($this->pivot($user, $course)->end_date);
        Event::assertDispatched(CourseAssigned::class);
    }

    public function testGrantingTheSameSourceTwiceIsIdempotent(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();

        $first = $this->grant($user, $course, 'k1');
        $second = $this->grant($user, $course, 'k1');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Entitlement::query()->count());
    }

    public function testARevokedSourceStaysRevokedWhenGrantedAgain(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->grant($user, $course, 'k1');
        $this->service->revoke('k1', 'refund');

        $again = $this->grant($user, $course, 'k1');

        $this->assertSame('revoked', $again->status);
        $this->assertNull($this->pivot($user, $course));
    }

    public function testRevokeRemovesAccessOnlyWhenNoOtherEntitlementRemains(): void
    {
        Event::fake([CourseUnassigned::class]);
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->grant($user, $course, 'order-a');
        $this->grant($user, $course, 'order-b');

        $this->service->revoke('order-a', 'refund');
        $this->assertNotNull($this->pivot($user, $course), 'order-b still grants it');
        Event::assertNotDispatched(CourseUnassigned::class);

        $revoked = $this->service->revoke('order-b', 'refund');
        $this->assertNull($this->pivot($user, $course));
        $this->assertSame(['revoked', 'refund'], [$revoked->status, $revoked->revoke_reason]);
        Event::assertDispatched(CourseUnassigned::class);
    }

    public function testRevokeIsIdempotentAndIgnoresUnknownSources(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->grant($user, $course, 'k1');

        $this->service->revoke('k1', 'refund');
        $revokedAt = Entitlement::query()->firstOrFail()->revoked_at;
        $this->service->revoke('k1', 'cancelled');

        $this->assertSame('refund', Entitlement::query()->firstOrFail()->revoke_reason);
        $this->assertEquals($revokedAt, Entitlement::query()->firstOrFail()->revoked_at);
        $this->assertNull($this->service->revoke('missing', 'refund'));
    }

    public function testTheLatestEndDateWinsAndOpenEndedBeatsDated(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $soon = now()->addDays(10)->startOfSecond();
        $later = now()->addDays(40)->startOfSecond();

        $this->grant($user, $course, 'a', $soon);
        $this->assertEquals($soon, $this->pivot($user, $course)->end_date);
        $this->grant($user, $course, 'b', $later);
        $this->assertEquals($later, $this->pivot($user, $course)->end_date);
        $this->grant($user, $course, 'c', null);
        $this->assertNull($this->pivot($user, $course)->end_date);

        $this->service->revoke('c', 'refund');
        $this->assertEquals($later, $this->pivot($user, $course)->end_date);
        $this->service->revoke('b', 'refund');
        $this->assertEquals($soon, $this->pivot($user, $course)->end_date);
    }

    public function testAnExpiredEntitlementDoesNotGrantAccess(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->grant($user, $course, 'old', now()->subDay());

        $this->assertFalse($this->service->hasAccess($user->getKey(), $course->getKey()));
    }

    public function testExistingAccessIsAdoptedSoARevokeCannotRemoveIt(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        app(CourseAccessServiceContract::class)->addAccessForUsers($course, [$user->getKey()]);

        $this->grant($user, $course, 'order-a');
        $this->service->revoke('order-a', 'refund');

        $this->assertNotNull($this->pivot($user, $course));
        $adopted = Entitlement::query()->where('source_type', 'migration')->firstOrFail();
        $this->assertSame("migration:course_user:{$course->getKey()}:{$user->getKey()}", $adopted->source_key);
        $this->assertTrue($this->service->hasAccess($user->getKey(), $course->getKey()));
    }

    public function testAdoptionKeepsTheExistingEndDate(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $end = now()->addDays(5)->startOfSecond();
        app(CourseAccessServiceContract::class)->addAccessForUsers($course, [$user->getKey() => ['end_date' => $end]]);

        $this->grant($user, $course, 'order-a');
        $this->service->revoke('order-a', 'refund');

        $this->assertEquals($end, $this->pivot($user, $course)->end_date);
    }

    public function testGrantForAMissingCourseFailsWithoutRows(): void
    {
        $user = User::factory()->create();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        try {
            $this->service->grantCourse($user->getKey(), 999999, 'order_item', 'k1');
        } finally {
            $this->assertSame(0, Entitlement::query()->count());
        }
    }

    public function testEntitlementsOfOtherUsersAndCoursesAreUntouched(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $one = Course::factory()->create();
        $two = Course::factory()->create();
        $this->grant($alice, $one, 'a1');
        $this->grant($bob, $one, 'b1');
        $this->grant($alice, $two, 'a2');

        $this->service->revoke('a1', 'refund');

        $this->assertNull($this->pivot($alice, $one));
        $this->assertNotNull($this->pivot($bob, $one));
        $this->assertNotNull($this->pivot($alice, $two));
    }
}
