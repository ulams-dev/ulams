<?php

namespace Ulams\Entitlements\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Ulams\Auth\Models\Group;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\CourseAccess\Database\Seeders\CourseAccessPermissionSeeder;
use Ulams\CourseAccess\Models\Course;
use Ulams\CourseAccess\Services\Contracts\CourseAccessServiceContract;
use Ulams\CourseAccess\Services\CourseAccessService;
use Ulams\CourseAccess\Support\AccessSourceContext;
use Ulams\Courses\Events\CourseAssigned;
use Ulams\Courses\Events\CourseUnassigned;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Courses\Models\User;
use Ulams\Entitlements\Models\Entitlement;
use Ulams\Entitlements\Services\EntitlementCourseAccess;
use Ulams\Entitlements\Services\EntitlementService;
use Ulams\Entitlements\Tests\TestCase;

class CourseAccessDecoratorTest extends TestCase
{
    use CreatesUsers;

    private CourseAccessServiceContract $access;

    protected function setUp(): void
    {
        parent::setUp();
        $this->access = app(CourseAccessServiceContract::class);
    }

    private function pivot(User $user, Course $course): ?CourseUserPivot
    {
        return CourseUserPivot::query()->where('user_id', $user->getKey())->where('course_id', $course->getKey())->first();
    }

    public function testTheContainerHandsOutTheDecoratorAndTheEntitlementsWriteThroughThePlainService(): void
    {
        $this->assertInstanceOf(EntitlementCourseAccess::class, $this->access);
        $this->assertSame($this->access, app(CourseAccessServiceContract::class), 'a singleton, not decorated twice');
        $this->assertInstanceOf(EntitlementService::class, app(EntitlementService::class));
    }

    public function testAttachingACourseRecordsAnAdminEntitlementAndKeepsTheEvents(): void
    {
        Event::fake([CourseAssigned::class]);
        $user = User::factory()->create();
        $course = Course::factory()->create();

        $this->access->addAccessForUsers($course, [$user->getKey()]);

        $this->assertNotNull($this->pivot($user, $course));
        $entitlement = Entitlement::query()->firstOrFail();
        $this->assertSame(['admin', 'active', $user->getKey(), $course->getKey()], [$entitlement->source_type, $entitlement->status, $entitlement->user_id, $entitlement->subject_id]);
        $this->assertNull($entitlement->valid_until);
        Event::assertDispatchedTimes(CourseAssigned::class, 1);
    }

    public function testTheAuthenticatedAdminIsRecordedAsTheGrantor(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create();
        $course = Course::factory()->create();

        $this->actingAs($admin, 'api');
        $this->access->addAccessForUsers($course, [$user->getKey()]);

        $this->assertSame((string) $admin->getKey(), Entitlement::query()->firstOrFail()->granted_by);
    }

    public function testAttachingAgainDoesNotPileUpEntitlementsAndAnExplicitEndDateMovesTheEnd(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $end = now()->addDays(7)->startOfSecond();

        $this->access->addAccessForUsers($course, [$user->getKey()]);
        $this->access->addAccessForUsers($course, [$user->getKey()]);
        $this->assertSame(1, Entitlement::query()->count());

        $this->access->addAccessForUsers($course, [$user->getKey() => ['end_date' => $end]]);
        $this->assertSame(1, Entitlement::query()->count());
        $this->assertEquals($end, Entitlement::query()->firstOrFail()->valid_until);
        $this->assertEquals($end, $this->pivot($user, $course)->end_date);
    }

    public function testAttachingWithoutAnEndDateKeepsTheEndOfAnExistingRow(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $end = now()->addDays(5)->startOfSecond();
        (new CourseAccessService())->addAccessForUsers($course, [$user->getKey() => ['end_date' => $end]]);

        $this->access->addAccessForUsers($course, [$user->getKey()]);

        $this->assertEquals($end, $this->pivot($user, $course)->end_date, 'adding must not extend access');
        $this->assertEqualsCanonicalizing(['admin', 'migration'], Entitlement::query()->pluck('source_type')->all());
        $this->assertEquals([$end, $end], Entitlement::query()->get()->pluck('valid_until')->all());
    }

    public function testExistingAccessIsAdoptedBeforeTheFirstWrite(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        (new CourseAccessService())->addAccessForUsers($course, [$user->getKey()]);

        $this->access->addAccessForUsers($course, [$user->getKey()]);

        $this->assertSame(['admin', 'migration'], Entitlement::query()->orderBy('source_type')->pluck('source_type')->all());
    }

    public function testTheSourceContextNamesLtiAndComplianceGrants(): void
    {
        $lti = User::factory()->create();
        $compliance = User::factory()->create();
        $course = Course::factory()->create();

        AccessSourceContext::as('lti', fn () => $this->access->addAccessForUsers($course, [$lti->getKey()]), ['platform' => 3]);
        AccessSourceContext::as('compliance', fn () => $this->access->addAccessForUsers($course, [$compliance->getKey()]));

        $this->assertSame('lti', Entitlement::query()->where('user_id', $lti->getKey())->firstOrFail()->source_type);
        $this->assertSame(3, Entitlement::query()->where('user_id', $lti->getKey())->firstOrFail()->source_ref['platform']);
        $this->assertSame('compliance', Entitlement::query()->where('user_id', $compliance->getKey())->firstOrFail()->source_type);
        $this->assertNull(AccessSourceContext::current(), 'the context is gone after the block');
    }

    public function testTheContextIsRestoredAfterAnException(): void
    {
        try {
            AccessSourceContext::as('lti', fn () => throw new \RuntimeException('boom'));
        } catch (\RuntimeException) {
        }
        $this->assertNull(AccessSourceContext::current());
        AccessSourceContext::as('lti', fn () => $this->assertSame('compliance', AccessSourceContext::as('compliance', fn () => AccessSourceContext::current()['type'])));
    }

    public function testRemovingARevokesEveryEntitlementAndTheRow(): void
    {
        Event::fake([CourseUnassigned::class]);
        $user = User::factory()->create();
        $course = Course::factory()->create();
        app(EntitlementService::class)->grantCourse($user->getKey(), $course->getKey(), 'order_item', 'sylius:acme:order_item:1');
        $this->access->addAccessForUsers($course, [$user->getKey()]);

        $this->access->removeAccessForUsers($course, [$user->getKey()]);

        $this->assertNull($this->pivot($user, $course));
        $this->assertSame(['revoked', 'revoked'], Entitlement::query()->pluck('status')->all());
        $this->assertSame(['admin_removed'], Entitlement::query()->pluck('revoke_reason')->unique()->values()->all());
        Event::assertDispatchedTimes(CourseUnassigned::class, 1);
    }

    public function testRemovingARowNothingExplainsStillRemovesIt(): void
    {
        Event::fake([CourseUnassigned::class]);
        $user = User::factory()->create();
        $course = Course::factory()->create();
        (new CourseAccessService())->addAccessForUsers($course, [$user->getKey()]);

        $this->access->removeAccessForUsers($course, [$user->getKey()]);

        $this->assertNull($this->pivot($user, $course));
        Event::assertDispatchedTimes(CourseUnassigned::class, 1);
    }

    public function testRemovingOneUserLeavesTheOthers(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $course = Course::factory()->create();
        $this->access->addAccessForUsers($course, [$alice->getKey(), $bob->getKey()]);

        $this->access->removeAccessForUsers($course, [$alice->getKey()]);

        $this->assertNull($this->pivot($alice, $course));
        $this->assertNotNull($this->pivot($bob, $course));
        $this->assertSame('active', Entitlement::query()->where('user_id', $bob->getKey())->firstOrFail()->status);
    }

    public function testAttachingAfterARemovalCreatesAFreshEntitlement(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->access->addAccessForUsers($course, [$user->getKey()]);
        $this->access->removeAccessForUsers($course, [$user->getKey()]);

        $this->access->addAccessForUsers($course, [$user->getKey()]);

        $this->assertNotNull($this->pivot($user, $course));
        $this->assertSame(['active', 'revoked'], Entitlement::query()->orderBy('status')->pluck('status')->all());
    }

    public function testSetAddsTheNewUsersAndRemovesTheOthers(): void
    {
        $keep = User::factory()->create();
        $drop = User::factory()->create();
        $new = User::factory()->create();
        $course = Course::factory()->create();
        $this->access->addAccessForUsers($course, [$keep->getKey(), $drop->getKey()]);

        $this->access->setAccessForUsers($course, [$keep->getKey(), $new->getKey()]);

        $this->assertNotNull($this->pivot($keep, $course));
        $this->assertNotNull($this->pivot($new, $course));
        $this->assertNull($this->pivot($drop, $course));
        $this->assertSame('revoked', Entitlement::query()->where('user_id', $drop->getKey())->firstOrFail()->status);
        $this->assertSame('active', Entitlement::query()->where('user_id', $new->getKey())->firstOrFail()->status);
    }

    public function testSettingAnEmptyListRemovesEveryone(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $this->access->addAccessForUsers($course, [$user->getKey()]);

        $this->access->setAccessForUsers($course, []);

        $this->assertSame(0, CourseUserPivot::query()->where('course_id', $course->getKey())->count());
    }

    public function testGroupAccessPassesThroughWithoutEntitlements(): void
    {
        $group = Group::factory()->create();
        $course = Course::factory()->create();

        $this->access->addAccessForGroups($course, [$group->getKey()]);
        $this->assertSame([$group->getKey()], $course->groups()->pluck('groups.id')->all());
        $this->access->setAccessForGroups($course, []);
        $this->assertSame(0, $course->groups()->count());
        $this->assertSame(0, Entitlement::query()->count());
    }

    public function testEmptyListsDoNothing(): void
    {
        $course = Course::factory()->create();

        $this->access->addAccessForUsers($course, []);
        $this->access->removeAccessForUsers($course, []);

        $this->assertSame(0, Entitlement::query()->count());
    }

    public function testTheAdminAccessEndpointRecordsAndRevokesEntitlements(): void
    {
        $this->seed(CourseAccessPermissionSeeder::class);
        $admin = $this->makeAdmin();
        $student = User::factory()->create();
        $course = Course::factory()->create();

        $this->actingAs($admin, 'api')->postJson("/api/admin/courses/{$course->getKey()}/access/add", ['users' => [$student->getKey()]])->assertOk();
        $entitlement = Entitlement::query()->firstOrFail();
        $this->assertSame(['admin', (string) $admin->getKey()], [$entitlement->source_type, $entitlement->granted_by]);
        $this->assertNotNull($this->pivot($student, $course));

        $this->actingAs($admin, 'api')->postJson("/api/admin/courses/{$course->getKey()}/access/remove", ['users' => [$student->getKey()]])->assertOk();
        $this->assertSame('revoked', $entitlement->refresh()->status);
        $this->assertNull($this->pivot($student, $course));
    }
}
