<?php

namespace Ulams\Commerce\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Ulams\Commerce\Models\DriftReport;
use Ulams\Commerce\Tests\Support\OrderFlowTestCase;
use Ulams\Entitlements\Models\Entitlement;

/** The nightly full sweep (`ulams:commerce:reconcile --full`): repairs both directions without reading any event. */
class OrderSweepTest extends OrderFlowTestCase
{
    private function sweep(array $options = []): int
    {
        return Artisan::call('ulams:commerce:reconcile', ['--full' => true] + $options);
    }

    public function testAPaidOrderWhoseEventNeverArrivedIsGrantedBySweep(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $this->sylius->pay('77'); // the event exists in the feed but nobody read it

        $this->assertSame(1, $this->sweep(['--dry-run' => true]), 'reported as drift');
        $this->assertFalse($this->hasAccess($user, $course), 'a dry run changes nothing');

        $this->assertSame(0, $this->sweep());
        $this->assertTrue($this->hasAccess($user, $course));
        $this->assertSame('fake:acme:order_item:991', Entitlement::query()->firstOrFail()->source_key);
        $this->assertDatabaseHas('commerce_orders', ['external_order_id' => '77', 'payment_state' => 'paid', 'user_id' => $user->getKey()]);
        $report = DriftReport::query()->where('kind', 'orders')->latest('id')->firstOrFail();
        $this->assertSame([1, 1], [$report->drift, $report->repaired]);
        $this->assertSame(['grant fake:acme:order_item:991'], array_map(fn ($c) => substr($c, strpos($c, ': ') + 2), $report->details['changes']));
    }

    public function testARefundTheLmsNeverHeardOfIsRevokedBySweep(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $this->deliver($this->sylius->pay('77'))->assertStatus(202);
        $this->assertTrue($this->hasAccess($user, $course));
        $this->sylius->refund('77'); // event lost

        $this->assertSame(0, $this->sweep());

        $this->assertFalse($this->hasAccess($user, $course));
        $this->assertSame('refund', Entitlement::query()->firstOrFail()->revoke_reason);
    }

    public function testACancelledOrderIsRevokedAndTheSweepIsIdempotent(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $this->deliver($this->sylius->pay('77'))->assertStatus(202);
        $this->sylius->cancel('77');

        $this->sweep();
        $this->assertFalse($this->hasAccess($user, $course));
        $this->assertSame('cancelled', Entitlement::query()->firstOrFail()->revoke_reason);

        $this->assertSame(0, $this->sweep());
        $this->assertSame(0, DriftReport::query()->latest('id')->firstOrFail()->drift);
    }

    public function testAnOrderOfAnotherChannelIsNeverTouched(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1'], 'globex');
        $this->sylius->pay('77');

        $this->assertSame(0, $this->sweep());

        $this->assertFalse($this->hasAccess($user, $course));
        $this->assertSame(0, Entitlement::query()->count());
        $this->assertDatabaseMissing('commerce_orders', ['external_order_id' => '77']);
    }

    public function testAnOrderForACustomerNobodyLinkedIsAProblemNotAGrant(): void
    {
        $this->course();
        $this->sylius->placeOrder('77', null, ['991' => 'variant-course-1']);
        $this->sylius->pay('77');

        $this->assertSame(1, $this->sweep());

        $this->assertSame(0, Entitlement::query()->count());
        $report = DriftReport::query()->latest('id')->firstOrFail();
        $this->assertStringContainsString('not linked to an LMS user', $report->details['problems'][0]);
    }

    public function testAProductNoCourseIsLinkedToIsReportedAndDoesNotStopTheOthers(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('1', $user->getKey(), ['10' => 'unknown-variant']);
        $this->sylius->placeOrder('2', $user->getKey(), ['20' => 'variant-course-1']);
        $this->sylius->pay('1');
        $this->sylius->pay('2');

        $this->assertSame(1, $this->sweep());

        $this->assertTrue($this->hasAccess($user, $course), 'the second order was still repaired');
        $this->assertStringContainsString('unknown-variant', DriftReport::query()->latest('id')->firstOrFail()->details['problems'][0]);
    }

    public function testAnUnpaidOrderIsIgnoredAndAnInvalidDateIsRefused(): void
    {
        $user = $this->user();
        $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);

        $this->assertSame(0, $this->sweep());
        $this->assertSame(0, Entitlement::query()->count());
        $this->assertSame(2, $this->sweep(['--since' => 'last tuesday']));
    }

    public function testAProviderThatCannotListOrdersHasNothingToSweep(): void
    {
        config(['commerce.provider' => 'wellms']);

        $this->assertSame(0, Artisan::call('ulams:commerce:reconcile', ['--full' => true]));
        $this->assertSame(0, DriftReport::query()->count());
    }

    public function testTheSweepNeedsTheTenantsChannel(): void
    {
        config(['commerce.channel' => null]);

        $this->assertSame(1, $this->sweep());
    }
}
