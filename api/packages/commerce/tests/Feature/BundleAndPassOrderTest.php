<?php

namespace Ulams\Commerce\Tests\Feature;

use Ulams\Commerce\Models\AccessPass;
use Ulams\Commerce\Models\Bundle;
use Ulams\Commerce\Models\BundleCourse;
use Ulams\Commerce\Services\CatalogueSync;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\SellableRef;
use Ulams\Commerce\Tests\Support\OrderFlowTestCase;
use Ulams\CourseAccess\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Entitlements\Models\Entitlement;

/** A bundle expands to one entitlement per course, an access pass ends after its days (ADR 0130, Q6). */
class BundleAndPassOrderTest extends OrderFlowTestCase
{
    private function sold(SellableRef $ref): string
    {
        app(CatalogueSync::class)->setPrice($ref, new Price(9900, 'EUR'));

        return app(CatalogueSync::class)->push($ref)->externalId;
    }

    /** @return array{Bundle, list<Course>} */
    private function bundle(int $courses = 2): array
    {
        $bundle = Bundle::query()->create(['name' => 'Coffee set']);
        $list = [];
        for ($i = 0; $i < $courses; $i++) {
            $list[] = $course = Course::factory()->create(['status' => 'published']);
            BundleCourse::query()->create(['bundle_id' => $bundle->getKey(), 'course_id' => $course->getKey()]);
        }

        return [$bundle, $list];
    }

    public function testABundleGrantsEveryCourseWithAKeyPerCourse(): void
    {
        [$bundle, [$a, $b]] = $this->bundle();
        $user = $this->user();
        $product = $this->sold(SellableRef::bundle($bundle->getKey()));
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => $product]);

        $this->deliver($this->sylius->pay('77'))->assertStatus(202);

        $this->assertTrue($this->hasAccess($user, $a));
        $this->assertTrue($this->hasAccess($user, $b));
        $this->assertEqualsCanonicalizing(
            ["fake:acme:order_item:991:course:{$a->getKey()}", "fake:acme:order_item:991:course:{$b->getKey()}"],
            Entitlement::query()->pluck('source_key')->all(),
        );
        $this->assertSame("bundle:{$bundle->getKey()}", Entitlement::query()->first()->source_ref['sellable']);
    }

    public function testRefundingABundleRevokesAllItsCourses(): void
    {
        [$bundle, [$a, $b]] = $this->bundle();
        $user = $this->user();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => $this->sold(SellableRef::bundle($bundle->getKey()))]);
        $this->deliver($this->sylius->pay('77'))->assertStatus(202);

        $this->deliver($this->sylius->refund('77'))->assertStatus(202);

        $this->assertFalse($this->hasAccess($user, $a));
        $this->assertFalse($this->hasAccess($user, $b));
        $this->assertSame(['revoked', 'revoked'], Entitlement::query()->pluck('status')->all());
    }

    public function testACourseBoughtSeparatelyKeepsItsAccessWhenTheBundleIsRefunded(): void
    {
        [$bundle, [$a, $b]] = $this->bundle();
        $user = $this->user();
        $single = $this->sold(SellableRef::course($a->getKey()));
        $this->sylius->placeOrder('1', $user->getKey(), ['10' => $single]);
        $this->sylius->placeOrder('2', $user->getKey(), ['20' => $this->sold(SellableRef::bundle($bundle->getKey()))]);
        $this->deliver($this->sylius->pay('1'))->assertStatus(202);
        $this->deliver($this->sylius->pay('2'))->assertStatus(202);

        $this->deliver($this->sylius->refund('2'))->assertStatus(202);

        $this->assertTrue($this->hasAccess($user, $a), 'another source still grants it');
        $this->assertFalse($this->hasAccess($user, $b));
    }

    public function testCoursesAddedToABundleLaterAreNotGrantedToPastBuyersButAreToNewOnes(): void
    {
        [$bundle, [$a]] = $this->bundle(1);
        $early = $this->user();
        $product = $this->sold(SellableRef::bundle($bundle->getKey()));
        $this->sylius->placeOrder('1', $early->getKey(), ['10' => $product]);
        $this->deliver($this->sylius->pay('1'))->assertStatus(202);

        $later = Course::factory()->create(['status' => 'published']);
        BundleCourse::query()->create(['bundle_id' => $bundle->getKey(), 'course_id' => $later->getKey()]);
        $new = $this->user();
        $this->sylius->placeOrder('2', $new->getKey(), ['20' => $product]);
        $this->deliver($this->sylius->pay('2'))->assertStatus(202);

        $this->assertFalse($this->hasAccess($early, $later));
        $this->assertTrue($this->hasAccess($new, $a));
        $this->assertTrue($this->hasAccess($new, $later));
    }

    public function testAnAccessPassEndsAfterItsDaysAndABundlePassCoversEveryCourse(): void
    {
        [$bundle, [$a, $b]] = $this->bundle();
        $pass = AccessPass::query()->create(['name' => 'Set, 90 days', 'subject_type' => 'bundle', 'subject_id' => $bundle->getKey(), 'days' => 90]);
        $user = $this->user();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => $this->sold(SellableRef::accessPass($pass->getKey()))]);

        $this->deliver($this->sylius->pay('77'))->assertStatus(202);

        foreach ([$a, $b] as $course) {
            $end = CourseUserPivot::query()->where(['user_id' => $user->getKey(), 'course_id' => $course->getKey()])->firstOrFail()->end_date;
            $this->assertEqualsWithDelta(now()->addDays(90)->getTimestamp(), $end->getTimestamp(), 60);
        }
        $this->assertEqualsCanonicalizing([90, 90], Entitlement::query()->get()->map(fn ($e) => (int) round($e->valid_until->diffInDays(now(), true)))->all());
    }

    public function testAnAccessPassOnACourseUsesTheCoursePlainKey(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $pass = AccessPass::query()->create(['name' => 'Brewing, 30 days', 'subject_type' => 'course', 'subject_id' => $course->getKey(), 'days' => 30]);
        $user = $this->user();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => $this->sold(SellableRef::accessPass($pass->getKey()))]);

        $this->deliver($this->sylius->pay('77'))->assertStatus(202);

        $this->assertSame(["fake:acme:order_item:991:course:{$course->getKey()}"], Entitlement::query()->pluck('source_key')->all());
        $this->assertTrue($this->hasAccess($user, $course));
    }

    public function testASecondPurchaseOfAPassIsAnotherEntitlementWithItsOwnEnd(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $pass = AccessPass::query()->create(['name' => 'Brewing, 30 days', 'subject_type' => 'course', 'subject_id' => $course->getKey(), 'days' => 30]);
        $user = $this->user();
        $product = $this->sold(SellableRef::accessPass($pass->getKey()));
        $this->sylius->placeOrder('1', $user->getKey(), ['10' => $product]);
        $this->sylius->placeOrder('2', $user->getKey(), ['20' => $product]);

        $this->deliver($this->sylius->pay('1'))->assertStatus(202);
        $this->travel(20)->days();
        $this->deliver($this->sylius->pay('2'))->assertStatus(202);

        $this->assertSame(2, Entitlement::query()->count());
        $end = CourseUserPivot::query()->where('user_id', $user->getKey())->firstOrFail()->end_date;
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), $end->getTimestamp(), 60, 'the later end wins');
    }
}
