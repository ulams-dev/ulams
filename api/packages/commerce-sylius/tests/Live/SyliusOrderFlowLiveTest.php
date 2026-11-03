<?php

namespace Ulams\CommerceSylius\Tests\Live;

use Illuminate\Support\Facades\Artisan;
use Ulams\Commerce\Models\AccessPass;
use Ulams\Commerce\Models\Bundle;
use Ulams\Commerce\Models\BundleCourse;
use Ulams\Commerce\Models\DriftReport;
use Ulams\Commerce\Services\CatalogueSync;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\SellableRef;
use Ulams\CommerceSylius\Tests\Support\LiveSylius;
use Ulams\CommerceSylius\Tests\TestCase;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Entitlements\Models\Entitlement;

/**
 * The flow that matters, against a real Sylius: catalogue sync, a real checkout, then access that follows the order
 * (through the event feed and through the nightly sweep) and goes away with a refund. The LMS only ever reads the
 * order back from Sylius; nothing here grants access from a redirect.
 */
class SyliusOrderFlowLiveTest extends TestCase
{
    private LiveSylius $live;

    protected function setUp(): void
    {
        parent::setUp();
        if (!LiveSylius::available()) {
            $this->markTestSkipped('No real Sylius: set COMMERCE_SYLIUS_LIVE_URL and the live admin credentials (compose profile `commerce`).');
        }
        $this->live = LiveSylius::get();
        config($this->live->config() + ['commerce.reconcile.page_size' => 50]);
    }

    private function sell(SellableRef $ref, int $amount = 4900): string
    {
        $sync = app(CatalogueSync::class);
        $sync->setPrice($ref, new Price($amount, 'EUR'));
        $sync->push($ref);

        return strtolower('ul-' . $this->live->channel . '-' . str_replace('_', '-', $ref->type) . '-' . $ref->id) . '-v';
    }

    private function reconcile(string ...$options): int
    {
        return Artisan::call('ulams:commerce:reconcile', array_fill_keys($options, true));
    }

    public function testAPaidOrderGrantsAccessThroughTheFeedAndARefundTakesItBack(): void
    {
        $user = $this->user();
        $course = $this->course();
        $variant = $this->sell(SellableRef::course($course->getKey()));

        $order = $this->live->paidOrder($user->getKey(), $variant);
        $this->assertFalse($this->hasAccess($user, $course), 'a checkout alone grants nothing');

        $this->assertSame(0, $this->reconcile());
        $this->assertTrue($this->hasAccess($user, $course));
        $entitlement = Entitlement::query()->where('user_id', $user->getKey())->firstOrFail();
        $this->assertSame(Entitlement::STATUS_ACTIVE, $entitlement->status);
        $this->assertStringStartsWith("sylius:{$this->live->channel}:order_item:", $entitlement->source_key);
        $this->assertDatabaseHas('commerce_orders', ['external_order_id' => $order['token'], 'payment_state' => 'paid', 'user_id' => $user->getKey()]);

        // running it again changes nothing (idempotent)
        $this->reconcile();
        $this->assertSame(1, Entitlement::query()->where('user_id', $user->getKey())->count());

        $this->live->refund($order['payment']);
        $this->reconcile();
        $this->assertFalse($this->hasAccess($user, $course));
        $this->assertSame(Entitlement::STATUS_REVOKED, $entitlement->refresh()->status);
        $this->assertSame('refund', $entitlement->revoke_reason);
    }

    public function testTheNightlySweepRepairsWithoutAnyEventBeingRead(): void
    {
        $user = $this->user();
        $course = $this->course();
        $variant = $this->sell(SellableRef::course($course->getKey()));
        $order = $this->live->paidOrder($user->getKey(), $variant);

        $this->assertSame(1, Artisan::call('ulams:commerce:reconcile', ['--full' => true, '--dry-run' => true]), 'a dry run reports drift (exit 1) and changes nothing');
        $this->assertFalse($this->hasAccess($user, $course));

        $this->assertSame(0, Artisan::call('ulams:commerce:reconcile', ['--full' => true]));
        $this->assertTrue($this->hasAccess($user, $course));
        $report = DriftReport::query()->where('kind', 'orders')->latest('id')->firstOrFail();
        $this->assertSame([1, 1], [$report->drift, $report->repaired]);

        $this->live->refund($order['payment']);
        $this->assertSame(0, Artisan::call('ulams:commerce:reconcile', ['--full' => true]));
        $this->assertFalse($this->hasAccess($user, $course));

        $this->assertSame(0, Artisan::call('ulams:commerce:reconcile', ['--full' => true]));
        $this->assertSame(0, DriftReport::query()->where('kind', 'orders')->latest('id')->firstOrFail()->drift, 'nothing left to repair');
    }

    public function testABundleGrantsEveryCourseAndARefundRevokesThemAll(): void
    {
        $user = $this->user();
        [$a, $b] = [$this->course(), $this->course()];
        $bundle = Bundle::query()->create(['name' => 'Coffee set']);
        foreach ([$a, $b] as $course) {
            BundleCourse::query()->create(['bundle_id' => $bundle->getKey(), 'course_id' => $course->getKey()]);
        }
        $variant = $this->sell(SellableRef::bundle($bundle->getKey()), 9900);

        $order = $this->live->paidOrder($user->getKey(), $variant);
        $this->reconcile();

        $this->assertTrue($this->hasAccess($user, $a));
        $this->assertTrue($this->hasAccess($user, $b));
        $this->assertSame(2, Entitlement::query()->where('user_id', $user->getKey())->where('status', 'active')->count());

        $this->live->refund($order['payment']);
        $this->reconcile();
        $this->assertFalse($this->hasAccess($user, $a));
        $this->assertFalse($this->hasAccess($user, $b));
    }

    public function testAnAccessPassEndsAfterItsDays(): void
    {
        $user = $this->user();
        $course = $this->course();
        $pass = AccessPass::query()->create(['name' => 'Brewing, 30 days', 'subject_type' => 'course', 'subject_id' => $course->getKey(), 'days' => 30]);
        $variant = $this->sell(SellableRef::accessPass($pass->getKey()), 1900);

        $this->live->paidOrder($user->getKey(), $variant);
        $this->reconcile();

        $end = CourseUserPivot::query()->where(['user_id' => $user->getKey(), 'course_id' => $course->getKey()])->firstOrFail()->end_date;
        $this->assertNotNull($end);
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), $end->getTimestamp(), 120);
    }

    public function testACustomerWithoutALinkToAnLmsUserNeverGetsAccess(): void
    {
        $user = $this->user();
        $course = $this->course();
        $variant = $this->sell(SellableRef::course($course->getKey()));

        $this->live->paidOrder($user->getKey(), $variant, linked: false);
        $this->reconcile();

        $this->assertFalse($this->hasAccess($user, $course));
        $this->assertSame(0, Entitlement::query()->count());
        $this->assertDatabaseHas('commerce_order_events', ['outcome' => 'rejected']);
    }

    public function testTheCatalogueCheckFindsDriftMadeByHandInSyliusAndRepairsIt(): void
    {
        $course = $this->course();
        $ref = SellableRef::course($course->getKey());
        $variant = $this->sell($ref);
        $code = substr($variant, 0, -2);
        $this->assertSame(0, Artisan::call('ulams:commerce:sync', ['--check' => true]));

        // somebody disables the product and changes the price in the Sylius admin
        $this->live->admin->put("/api/v2/admin/products/{$code}", ['enabled' => false]);
        $pricing = $this->live->admin->get("/api/v2/admin/product-variants/{$variant}")['channelPricings'][$this->live->channel]['@id'];
        $this->live->admin->put("/api/v2/admin/product-variants/{$variant}", ['channelPricings' => [$this->live->channel => ['@id' => $pricing, 'channelCode' => $this->live->channel, 'price' => 100]]]);

        $this->assertSame(1, Artisan::call('ulams:commerce:sync', ['--check' => true]));
        $report = DriftReport::query()->where('kind', 'catalogue')->latest('id')->firstOrFail();
        $this->assertEqualsCanonicalizing(['price', 'enabled'], array_column($report->details, 'kind'));

        $this->assertSame(0, Artisan::call('ulams:commerce:sync', ['--check' => true, '--repair' => true]));
        $this->assertSame(0, Artisan::call('ulams:commerce:sync', ['--check' => true]), 'repaired');
        $this->assertSame(4900, app(\Ulams\Commerce\CommerceManager::class)->provider()->inspectProduct(new \Ulams\Commerce\Support\ProductRef('sylius', $code))->priceMinor);
    }

    public function testUnpublishingACourseDisablesItsProductInSylius(): void
    {
        $course = $this->course();
        $ref = SellableRef::course($course->getKey());
        $this->sell($ref);
        $code = strtolower("ul-{$this->live->channel}-course-{$course->getKey()}");
        $provider = app(\Ulams\Commerce\CommerceManager::class)->provider();
        $this->assertTrue($provider->inspectProduct(new \Ulams\Commerce\Support\ProductRef('sylius', $code))->enabled);

        $course->update(['status' => 'draft']); // fires CourseStatusChanged; the queue is sync here

        $this->assertFalse($provider->inspectProduct(new \Ulams\Commerce\Support\ProductRef('sylius', $code))->enabled);
        $course->update(['status' => 'published']);
        $this->assertTrue($provider->inspectProduct(new \Ulams\Commerce\Support\ProductRef('sylius', $code))->enabled);
    }
}
