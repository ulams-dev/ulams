<?php

namespace Ulams\Commerce\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\Cart\Enums\OrderStatus;
use Ulams\Commerce\Tests\Support\OrderFlowTestCase;
use Ulams\Commerce\Tests\Support\WellmsFixtures;
use Ulams\CourseAccess\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Entitlements\Models\Entitlement;
use Ulams\Entitlements\Models\ParityReport;
use Ulams\Entitlements\Services\EntitlementService;

class WellmsAccessBackfillTest extends OrderFlowTestCase
{
    use WellmsFixtures;

    private function snapshot(): array
    {
        return [DB::table('course_user')->orderBy('id')->get()->toArray(), DB::table('products_users')->orderBy('id')->get()->toArray()];
    }

    public function testEveryExistingRowGetsAnEntitlementAndNoAccessChanges(): void
    {
        $alice = $this->user();
        $bob = $this->user();
        $carol = $this->user();
        $paid = Course::factory()->create();
        $manual = Course::factory()->create();
        $lapsed = Course::factory()->create();
        $product = $this->product([$paid]);
        $line = $this->order($alice, $product);
        $this->ownsProduct($alice, $product);
        $this->cartAttach($alice, $paid);
        $this->cartAttach($bob, $manual);                       // an admin attached it: nobody explains it
        $this->cartAttach($carol, $lapsed, now()->subDays(10)); // access that ended long ago
        $before = $this->snapshot();

        $this->artisan('ulams:entitlements:backfill')->expectsOutputToContain('0 difference(s)')->assertSuccessful();

        $this->assertEquals($before, $this->snapshot(), 'the backfill never writes course_user or products_users');
        $byUser = Entitlement::query()->get()->keyBy('user_id');
        $this->assertSame(['wellms_order', "wellms:order_item:{$line->getKey()}:course:{$paid->getKey()}", 'active'], [$byUser[$alice->getKey()]->source_type, $byUser[$alice->getKey()]->source_key, $byUser[$alice->getKey()]->status]);
        $this->assertSame(['migration', "migration:course_user:{$manual->getKey()}:{$bob->getKey()}"], [$byUser[$bob->getKey()]->source_type, $byUser[$bob->getKey()]->source_key]);
        $this->assertSame('expired', $byUser[$carol->getKey()]->status);
        $report = ParityReport::query()->firstOrFail();
        $this->assertSame(['backfill', 'passed', 0, 3], [$report->kind, $report->status, $report->differences, $report->entitlements_created]);
        $this->assertSame($report->snapshot_before, $report->snapshot_after);
    }

    public function testRunningItAgainCreatesNothing(): void
    {
        $this->cartAttach($this->user(), Course::factory()->create());

        $this->artisan('ulams:entitlements:backfill')->assertSuccessful();
        $this->artisan('ulams:entitlements:backfill')->assertSuccessful();

        $this->assertSame(1, Entitlement::query()->count());
        $this->assertSame([1, 0], ParityReport::query()->orderBy('id')->pluck('entitlements_created')->all());
    }

    public function testADryRunReportsAndWritesNothing(): void
    {
        $this->cartAttach($this->user(), Course::factory()->create());

        $this->artisan('ulams:entitlements:backfill --dry-run')->expectsOutputToContain('Dry run: 1 course_user row(s)')->assertSuccessful();

        $this->assertSame(0, Entitlement::query()->count());
        $report = ParityReport::query()->firstOrFail();
        $this->assertSame(['dry_run', 'passed', 0], [$report->kind, $report->status, $report->entitlements_created]);
        $this->assertSame(1, $report->details['would_create']['course_user_rows']['migration']);
    }

    public function testOnlyPaidOrdersExplainAccess(): void
    {
        $user = $this->user();
        $course = Course::factory()->create();
        $product = $this->product([$course]);
        $this->order($user, $product, OrderStatus::CANCELLED);
        $this->cartAttach($user, $course);

        $this->artisan('ulams:entitlements:backfill')->assertSuccessful();

        $this->assertSame('migration', Entitlement::query()->firstOrFail()->source_type);
    }

    public function testOneProductWithTwoCoursesGivesEachItsOwnKey(): void
    {
        $user = $this->user();
        [$one, $two] = [Course::factory()->create(), Course::factory()->create()];
        $line = $this->order($user, $this->product([$one, $two]));
        $this->cartAttach($user, $one);
        $this->cartAttach($user, $two);

        $this->artisan('ulams:entitlements:backfill')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            ["wellms:order_item:{$line->getKey()}:course:{$one->getKey()}", "wellms:order_item:{$line->getKey()}:course:{$two->getKey()}"],
            Entitlement::query()->pluck('source_key')->all(),
        );
    }

    public function testASubscriptionBecomesAnEntitlementThatNeverOutlivesTheRow(): void
    {
        $user = $this->user();
        $course = Course::factory()->create();
        $product = $this->product([$course]);
        $end = now()->addDays(20)->startOfSecond();
        $this->ownsProduct($user, $product, $end);
        $subId = DB::table('products_users')->value('id');
        $this->cartAttach($user, $course, $end);

        $this->artisan('ulams:entitlements:backfill')->assertSuccessful();

        $entitlements = Entitlement::query()->orderBy('source_key')->get();
        $this->assertCount(2, $entitlements);
        $subscription = $entitlements->firstWhere('source_key', "wellms:product_user:{$subId}:course:{$course->getKey()}");
        $this->assertEquals($end, $subscription->valid_until);
        $this->assertSame('wellms_order', $subscription->source_type);
        $this->assertEquals($end, CourseUserPivot::query()->firstOrFail()->end_date);
    }

    public function testASubscriptionLongerThanTheRowIsClampedToTheRow(): void
    {
        $user = $this->user();
        $course = Course::factory()->create();
        $rowEnd = now()->addDays(5)->startOfSecond();
        $this->ownsProduct($user, $this->product([$course]), now()->addDays(50));
        $this->cartAttach($user, $course, $rowEnd);

        $this->artisan('ulams:entitlements:backfill')->assertSuccessful();

        $this->assertEquals([$rowEnd, $rowEnd], Entitlement::query()->get()->pluck('valid_until')->all());
    }

    public function testASubscriptionWithoutACourseRowIsCountedNotInvented(): void
    {
        $user = $this->user();
        $this->ownsProduct($user, $this->product([Course::factory()->create()]), now()->addDays(20));

        $this->artisan('ulams:entitlements:backfill')->assertSuccessful();

        $this->assertSame(0, Entitlement::query()->count());
        $this->assertSame(1, ParityReport::query()->firstOrFail()->details['subscriptions_without_course_row']);
    }

    public function testAnEntitlementThatAlreadyExplainsTheRowIsLeftAlone(): void
    {
        $user = $this->user();
        $course = Course::factory()->create();
        app(EntitlementService::class)->grantCourse($user->getKey(), $course->getKey(), 'order_item', 'sylius:acme:order_item:1');

        $this->artisan('ulams:entitlements:backfill')->assertSuccessful();

        $this->assertSame(['order_item'], Entitlement::query()->pluck('source_type')->all(), 'a refund must still be able to revoke it');
    }

    public function testADifferenceFailsTheRunAndRollsEverythingBack(): void
    {
        $fine = $this->user();
        $odd = $this->user();
        [$one, $two] = [Course::factory()->create(), Course::factory()->create()];
        $this->cartAttach($fine, $one);
        // an entitlement ends tomorrow while the row never ends: the backfill leaves it alone and parity differs
        app(EntitlementService::class)->grantCourse($odd->getKey(), $two->getKey(), 'order_item', 'sylius:acme:order_item:9', [], now()->addDay());
        DB::table('course_user')->where('user_id', $odd->getKey())->update(['end_date' => null]);
        $entitlements = Entitlement::query()->count();

        $this->artisan('ulams:entitlements:backfill')->expectsOutputToContain('nothing was written')->assertFailed();

        $this->assertSame($entitlements, Entitlement::query()->count(), 'all or nothing');
        $report = ParityReport::query()->firstOrFail();
        $this->assertSame(['backfill', 'failed', 1, 0], [$report->kind, $report->status, $report->differences, $report->entitlements_created]);
        $this->assertSame('end_date_mismatch', $report->details['sample'][0]['kind']);
        $this->assertTrue($report->details['rolled_back']);
    }

    public function testAfterTheBackfillTheNightlyParityCheckPasses(): void
    {
        $user = $this->user();
        $course = Course::factory()->create();
        $this->order($user, $this->product([$course]));
        $this->cartAttach($user, $course);
        $this->cartAttach($this->user(), Course::factory()->create());

        $this->artisan('ulams:entitlements:backfill')->assertSuccessful();
        $this->artisan('ulams:entitlements:parity')->expectsOutputToContain('2 pair(s) checked, 0 difference(s)')->assertSuccessful();
    }

    public function testTheCommandIsRegistered(): void
    {
        $this->assertArrayHasKey('ulams:entitlements:backfill', \Illuminate\Support\Facades\Artisan::all());
    }
}
