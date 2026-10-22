<?php

namespace Ulams\Entitlements\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\CourseAccess\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Courses\Models\User;
use Ulams\Entitlements\Models\Entitlement;
use Ulams\Entitlements\Services\EntitlementService;
use Ulams\Entitlements\Tests\TestCase;

class ExpiryTest extends TestCase
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

    /** An entitlement that was in force when granted and has run out since. */
    private function grantThenLapse(User $user, Course $course, string $key, $lapsedAt): Entitlement
    {
        $entitlement = $this->service->grantCourse($user->getKey(), $course->getKey(), 'order_item', $key, [], now()->addDay());
        DB::table('entitlements')->where('id', $entitlement->id)->update(['valid_until' => $lapsedAt]);

        return $entitlement->refresh();
    }

    public function testTheJobMarksLapsedEntitlementsExpired(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $lapsed = $this->grantThenLapse($user, $course, 'a', now()->subHour());
        $open = $this->service->grantCourse(User::factory()->create()->getKey(), $course->getKey(), 'order_item', 'b');
        $future = $this->service->grantCourse(User::factory()->create()->getKey(), $course->getKey(), 'order_item', 'c', [], now()->addDays(3));

        $this->assertSame(1, $this->service->expireDue());

        $this->assertSame('expired', $lapsed->refresh()->status);
        $this->assertSame('active', $open->refresh()->status);
        $this->assertSame('active', $future->refresh()->status);
    }

    public function testTheCourseRowStaysWithTheLapsedEndDateSoProgressSurvives(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $lapsedAt = now()->subDays(2)->startOfSecond();
        $this->grantThenLapse($user, $course, 'a', $lapsedAt);
        CourseUserPivot::query()->where('user_id', $user->getKey())->update(['finished' => true]);

        $this->service->expireDue();

        $row = $this->pivot($user, $course);
        $this->assertNotNull($row, 'an expired learner keeps the row, and with it progress');
        $this->assertTrue((bool) $row->finished);
        $this->assertEquals($lapsedAt, $row->end_date);
        $this->assertFalse($course->refresh()->hasUser($user));
        $this->assertFalse($this->service->hasAccess($user->getKey(), $course->getKey()));
    }

    public function testAnotherEntitlementInForceKeepsAccessAndTheLaterEnd(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $later = now()->addDays(30)->startOfSecond();
        $this->grantThenLapse($user, $course, 'a', now()->subMinute());
        $this->service->grantCourse($user->getKey(), $course->getKey(), 'order_item', 'b', [], $later);

        $this->service->expireDue();

        $this->assertEquals($later, $this->pivot($user, $course)->end_date);
        $this->assertTrue($this->service->hasAccess($user->getKey(), $course->getKey()));
    }

    public function testRunningTwiceIsANoOp(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->grantThenLapse($user, $course, 'a', now()->subHour());

        $this->assertSame(1, $this->service->expireDue());
        $this->assertSame(0, $this->service->expireDue());
    }

    public function testARevokedEntitlementIsNotTurnedIntoAnExpiredOne(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->grantThenLapse($user, $course, 'a', now()->subHour());
        $this->service->revoke('a', 'refund');

        $this->assertSame(0, $this->service->expireDue());
        $this->assertSame('revoked', Entitlement::query()->firstOrFail()->status);
    }

    public function testARePurchaseAfterExpiryRestoresAccessWithProgress(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->grantThenLapse($user, $course, 'a', now()->subDays(3));
        CourseUserPivot::query()->where('user_id', $user->getKey())->update(['finished' => true]);
        $this->service->expireDue();

        $this->service->grantCourse($user->getKey(), $course->getKey(), 'order_item', 'again', [], now()->addDays(30));

        $row = $this->pivot($user, $course);
        $this->assertTrue((bool) $row->finished);
        $this->assertTrue($course->refresh()->hasUser($user));
    }

    public function testTheCommandReportsTheCount(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->grantThenLapse($user, $course, 'a', now()->subHour());

        $this->artisan('ulams:entitlements:expire')->expectsOutput('Expired 1 entitlement(s).')->assertSuccessful();
        $this->artisan('ulams:entitlements:expire')->expectsOutput('Expired 0 entitlement(s).')->assertSuccessful();
    }

    public function testTheCommandsAreScheduled(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());

        $this->assertTrue($events->contains(fn ($e) => str_contains($e->command, 'ulams:entitlements:expire') && $e->expression === '0 * * * *'));
        $parity = $events->first(fn ($e) => str_contains($e->command, 'ulams:entitlements:parity'));
        $this->assertSame('30 3 * * *', $parity->expression);
    }

    public function testTheNightlyParityCheckWaitsForTheBackfill(): void
    {
        $parity = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'ulams:entitlements:parity'));
        $due = fn () => $parity->filtersPass($this->app);

        $this->assertFalse($due(), 'before the backfill every row would be a difference');
        \Ulams\Entitlements\Models\ParityReport::query()->create(['kind' => 'dry_run', 'status' => 'passed']);
        $this->assertFalse($due(), 'a dry run does not count');
        \Ulams\Entitlements\Models\ParityReport::query()->create(['kind' => 'backfill', 'status' => 'failed']);
        $this->assertFalse($due());
        \Ulams\Entitlements\Models\ParityReport::query()->create(['kind' => 'backfill', 'status' => 'passed']);
        $this->assertTrue($due());
    }
}
