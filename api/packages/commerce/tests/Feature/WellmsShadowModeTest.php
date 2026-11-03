<?php

namespace Ulams\Commerce\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\Cart\Events\ProductAttached;
use Ulams\Cart\Events\ProductBought;
use Ulams\Commerce\Tests\Support\OrderFlowTestCase;
use Ulams\Commerce\Tests\Support\WellmsFixtures;
use Ulams\CourseAccess\Models\Course;
use Ulams\Courses\Events\CourseAssigned;
use Ulams\Entitlements\Models\Entitlement;

/** The Wellms cart keeps selling (ADR 0130, shadow mode); every purchase is also recorded as an entitlement. */
class WellmsShadowModeTest extends OrderFlowTestCase
{
    use WellmsFixtures;

    private function buy($user, $product, $line): void
    {
        event(new ProductBought($product, $line->order, $user));
    }

    public function testAPurchaseRecordsAWellmsOrderEntitlementWithoutTouchingCourseUser(): void
    {
        $user = $this->user();
        $course = Course::factory()->create();
        $product = $this->product([$course]);
        $line = $this->order($user, $product);

        $this->buy($user, $product, $line);

        $entitlement = Entitlement::query()->firstOrFail();
        $this->assertSame(['wellms_order', "wellms:order_item:{$line->getKey()}:course:{$course->getKey()}", 'active', $user->getKey()], [$entitlement->source_type, $entitlement->source_key, $entitlement->status, $entitlement->user_id]);
        $this->assertSame(['provider' => 'wellms', 'order_id' => $line->order_id, 'order_item_id' => $line->getKey(), 'product_id' => $product->getKey()], $entitlement->source_ref);
        $this->assertFalse($this->hasAccess($user, $course), 'the cart attaches the course itself, with its own events');
    }

    public function testTheCartsOwnAttachmentThenMatchesTheEntitlement(): void
    {
        $user = $this->user();
        $course = Course::factory()->create();
        $product = $this->product([$course]);
        $line = $this->order($user, $product);

        $this->buy($user, $product, $line);
        $this->cartAttach($user, $course);
        event(new ProductAttached($product, $user, 1));

        $this->artisan('ulams:entitlements:parity')->expectsOutputToContain('1 pair(s) checked, 0 difference(s)')->assertSuccessful();
    }

    public function testASubscriptionEndWrittenByTheCartIsFollowedByTheEntitlement(): void
    {
        $user = $this->user();
        $course = Course::factory()->create();
        $product = $this->product([$course]);
        $line = $this->order($user, $product);
        $end = now()->addDays(30)->startOfSecond();

        $this->buy($user, $product, $line);
        $this->cartAttach($user, $course, $end);
        event(new ProductAttached($product, $user, 1));

        $this->assertEquals($end, Entitlement::query()->firstOrFail()->valid_until);
        $this->artisan('ulams:entitlements:parity')->assertSuccessful();
    }

    public function testBuyingTwiceRecordsOneEntitlementPerOrderLine(): void
    {
        $user = $this->user();
        $course = Course::factory()->create();
        $product = $this->product([$course]);
        $first = $this->order($user, $product);
        $second = $this->order($user, $product);

        $this->buy($user, $product, $first);
        $this->buy($user, $product, $first); // the same event twice
        $this->buy($user, $product, $second);

        $this->assertSame(2, Entitlement::query()->count());
    }

    public function testAProductWithSeveralCoursesRecordsEach(): void
    {
        $user = $this->user();
        [$one, $two] = [Course::factory()->create(), Course::factory()->create()];
        $product = $this->product([$one, $two]);
        $line = $this->order($user, $product);

        $this->buy($user, $product, $line);

        $this->assertEqualsCanonicalizing([$one->getKey(), $two->getKey()], Entitlement::query()->pluck('subject_id')->all());
    }

    public function testAProductWithoutCoursesRecordsNothing(): void
    {
        $user = $this->user();
        $product = $this->product([]);

        $this->buy($user, $product, $this->order($user, $product));

        $this->assertSame(0, Entitlement::query()->count());
    }

    public function testAFailureNeverBlocksThePurchase(): void
    {
        $user = $this->user();
        $course = Course::factory()->create();
        $good = Course::factory()->create();
        $product = $this->product([$course, $good]);
        $line = $this->order($user, $product);
        DB::table('courses')->where('id', $course->getKey())->delete(); // the first course is gone

        $this->buy($user, $product, $line); // must not throw

        $this->assertSame([$good->getKey()], Entitlement::query()->pluck('subject_id')->all(), 'the other course is still recorded');
    }

    public function testItCanBeSwitchedOff(): void
    {
        config(['commerce.shadow.wellms_entitlements' => false]);
        $user = $this->user();
        $product = $this->product([Course::factory()->create()]);

        $this->buy($user, $product, $this->order($user, $product));

        $this->assertSame(0, Entitlement::query()->count());
    }

    public function testAnAdminsEarlierAccessIsAdoptedSoALaterRefundCannotRemoveIt(): void
    {
        $user = $this->user();
        $course = Course::factory()->create();
        $this->cartAttach($user, $course);
        $product = $this->product([$course]);

        $this->buy($user, $product, $this->order($user, $product));

        $this->assertEqualsCanonicalizing(['migration', 'wellms_order'], Entitlement::query()->pluck('source_type')->all());
    }

    public function testNoDuplicateAssignmentEventIsRaised(): void
    {
        \Illuminate\Support\Facades\Event::fake([CourseAssigned::class]);
        $user = $this->user();
        $product = $this->product([Course::factory()->create()]);

        $this->buy($user, $product, $this->order($user, $product));

        \Illuminate\Support\Facades\Event::assertNotDispatched(CourseAssigned::class);
    }
}
