<?php

namespace Ulams\Commerce\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Ulams\Commerce\Jobs\ApplyOrderEvent;
use Ulams\Commerce\Models\OrderEventRecord;
use Ulams\Commerce\Models\OrderProjection;
use Ulams\Commerce\Models\SyncState;
use Ulams\Commerce\Services\UnlinkedProduct;
use Ulams\Commerce\Tests\Support\OrderFlowTestCase;
use Ulams\CourseAccess\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Entitlements\Models\Entitlement;
use Ulams\Entitlements\Services\EntitlementService;

/**
 * "Commerce E2E" of the quality bar (buy -> webhook -> access granted; refund -> access revoked; missed webhook
 * repaired by reconciliation) as feature tests against the fake provider, plus the ordering and verification rules
 * of ADR 0130 section 5.3.
 */
class OrderFlowTest extends OrderFlowTestCase
{
    private const KEY = 'fake:acme:order_item:991';

    /** The queue accepts jobs and never runs them: a dead worker. */
    private function loseQueuedJobs(): void
    {
        config(['queue.connections.lost' => ['driver' => 'null'], 'queue.default' => 'lost']);
    }

    private function restoreQueue(): void
    {
        config(['queue.default' => 'sync']);
    }

    public function testPaidOrderGrantsAccessOnlyAfterTheWebhookAndTheRefetch(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $this->assertFalse($this->hasAccess($user, $course), 'placing an order grants nothing');

        $this->deliver($this->sylius->pay('77'))->assertStatus(202);

        $this->assertTrue($this->hasAccess($user, $course));
        $entitlement = Entitlement::query()->where('source_key', self::KEY)->firstOrFail();
        $this->assertSame([Entitlement::STATUS_ACTIVE, Entitlement::SOURCE_ORDER_ITEM, $user->getKey(), $course->getKey()], [$entitlement->status, $entitlement->source_type, $entitlement->user_id, $entitlement->subject_id]);
        $this->assertNull($entitlement->valid_until);
        $this->assertSame('000' . '77', $entitlement->source_ref['order']);
        $order = OrderProjection::query()->where('external_order_id', '77')->firstOrFail();
        $this->assertSame(['paid', 1, $user->getKey()], [$order->payment_state, $order->last_sequence, $order->user_id]);
        $this->assertDatabaseHas('commerce_order_events', ['order_id' => '77', 'outcome' => 'applied']);
    }

    public function testAForgedEventForAnUnpaidOrderGrantsNothing(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        // the attacker holds the secret and claims a payment Sylius never recorded
        $forged = ['version' => 'v1', 'id' => '01HFORGED', 'type' => 'order.paid', 'channel_code' => 'acme', 'order_id' => '77', 'sequence' => 1];

        $this->deliver($forged)->assertStatus(202);

        $this->assertFalse($this->hasAccess($user, $course));
        $this->assertSame(0, Entitlement::query()->count());
        $event = OrderEventRecord::query()->where('event_id', '01HFORGED')->firstOrFail();
        $this->assertSame('rejected', $event->outcome);
        $this->assertStringContainsString('awaiting_payment', $event->error);
    }

    public function testAnOrderOfAnotherChannelIsRefusedByTheRefetch(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1'], 'globex');
        $event = $this->sylius->pay('77');
        $event['channel_code'] = 'acme'; // a lying payload, signed with this tenant's secret

        $this->deliver($event);

        $this->assertFalse($this->hasAccess($user, $course));
        $this->assertSame('rejected', OrderEventRecord::query()->firstOrFail()->outcome);
    }

    public function testAnOrderWithoutALinkedLmsUserIsRejected(): void
    {
        $this->course();
        $this->sylius->placeOrder('77', null, ['991' => 'variant-course-1']);

        $this->deliver($this->sylius->pay('77'));

        $this->assertSame(0, Entitlement::query()->count());
        $this->assertStringContainsString('not linked', OrderEventRecord::query()->firstOrFail()->error);
    }

    public function testAFullRefundRevokesAccessAndKeepsProgressRows(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $this->deliver($this->sylius->pay('77'));
        $this->assertTrue($this->hasAccess($user, $course));

        $this->deliver($this->sylius->refund('77'))->assertStatus(202);

        $this->assertFalse($this->hasAccess($user, $course));
        $entitlement = Entitlement::query()->where('source_key', self::KEY)->firstOrFail();
        $this->assertSame([Entitlement::STATUS_REVOKED, 'refund'], [$entitlement->status, $entitlement->revoke_reason]);
        $this->assertNotNull($entitlement->revoked_at);
        $this->assertSame('refunded', OrderProjection::query()->firstOrFail()->payment_state);
    }

    public function testACancelledOrderRevokesLikeARefund(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $this->deliver($this->sylius->pay('77'));

        $this->deliver($this->sylius->cancel('77'));

        $this->assertFalse($this->hasAccess($user, $course));
        $this->assertSame('cancelled', Entitlement::query()->firstOrFail()->revoke_reason);
    }

    public function testARefundCoveringAllUnitsOfOneLineRevokesOnlyThatLine(): void
    {
        $user = $this->user();
        $one = $this->course('variant-a');
        $two = $this->course('variant-b');
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-a', '992' => 'variant-b']);
        $this->deliver($this->sylius->pay('77'));

        $this->deliver($this->sylius->refund('77', ['991']));

        $this->assertFalse($this->hasAccess($user, $one));
        $this->assertTrue($this->hasAccess($user, $two));
    }

    public function testAGoodwillAmountRefundKeepsAccess(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $this->deliver($this->sylius->pay('77'));

        $this->deliver($this->sylius->refundAmount('77'));

        $this->assertTrue($this->hasAccess($user, $course));
        $this->assertSame('partially_refunded', OrderProjection::query()->firstOrFail()->payment_state);
    }

    public function testRefundingAPurchaseNeverRemovesAccessAnotherSourceGrants(): void
    {
        $user = $this->user();
        $course = $this->course();
        // an admin attached the course before the purchase (no entitlement exists for it yet)
        app(\Ulams\CourseAccess\Services\Contracts\CourseAccessServiceContract::class)->addAccessForUsers($course, [$user->getKey()]);
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $this->deliver($this->sylius->pay('77'));
        $this->assertSame(2, Entitlement::query()->count(), 'the earlier access is recorded as a migration entitlement');

        $this->deliver($this->sylius->refund('77'));

        $this->assertTrue($this->hasAccess($user, $course));
        $this->assertSame(Entitlement::SOURCE_MIGRATION, Entitlement::query()->inForce()->firstOrFail()->source_type);
    }

    public function testOutOfOrderDeliveryConvergesToTheTrueState(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $paid = $this->sylius->pay('77');
        $refunded = $this->sylius->refund('77');

        $this->deliver($refunded);      // sequence 2 arrives first
        $this->deliver($paid);          // sequence 1 arrives late

        $this->assertFalse($this->hasAccess($user, $course), 'the late "paid" must not resurrect access');
        $this->assertSame(0, Entitlement::query()->count());
        $this->assertSame(['applied', 'stale'], OrderEventRecord::query()->orderBy('sequence', 'desc')->pluck('outcome')->all());
        $this->assertSame(2, OrderProjection::query()->firstOrFail()->last_sequence);
    }

    public function testAnEventWithAnOldSequenceIsStale(): void
    {
        $user = $this->user();
        $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $paid = $this->sylius->pay('77');
        $this->deliver($paid);
        $this->deliver($this->sylius->refund('77'));
        $fetches = $this->sylius->fetches;

        $replayWithNewId = ['id' => 'another-id'] + $paid; // same sequence, new event id: not a duplicate by id
        $this->deliver($replayWithNewId);

        $this->assertSame('stale', OrderEventRecord::query()->where('event_id', 'another-id')->firstOrFail()->outcome);
        $this->assertSame($fetches, $this->sylius->fetches);
        $this->assertSame(Entitlement::STATUS_REVOKED, Entitlement::query()->firstOrFail()->status);
    }

    public function testWhenTheAdminApiIsDownTheEventStaysUnappliedAndTheJobRetries(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $event = $this->sylius->pay('77');
        $this->sylius->down = true;
        $this->loseQueuedJobs();

        $this->deliver($event)->assertStatus(202);
        $record = OrderEventRecord::query()->firstOrFail();
        try {
            (new ApplyOrderEvent($record->id))->handle(app(\Ulams\Commerce\Services\OrderEventApplier::class));
            $this->fail('the job must fail so that the queue retries it');
        } catch (RuntimeException) {
        }

        $record->refresh();
        $this->assertNull($record->outcome);
        $this->assertSame(1, $record->attempts);
        $this->assertStringContainsString('unreachable', $record->error);
        $this->assertFalse($this->hasAccess($user, $course));
        $this->assertSame(0, OrderProjection::query()->where('last_sequence', '>', 0)->count(), 'a failed attempt does not advance the sequence');

        $this->sylius->down = false;
        (new ApplyOrderEvent($record->id))->handle(app(\Ulams\Commerce\Services\OrderEventApplier::class));
        $this->assertTrue($this->hasAccess($user, $course));
    }

    public function testAJobThatGivesUpMarksTheEventFailed(): void
    {
        $user = $this->user();
        $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $this->loseQueuedJobs();
        $this->deliver($this->sylius->pay('77'));
        $record = OrderEventRecord::query()->firstOrFail();

        (new ApplyOrderEvent($record->id))->failed(new RuntimeException('gave up'));

        $this->assertSame(['failed', 'gave up'], [$record->refresh()->outcome, $record->error]);
    }

    public function testAPaidLineWithoutALinkedCourseFailsLoudlyInsteadOfSkippingTheGrant(): void
    {
        $user = $this->user();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-unknown']);
        $this->loseQueuedJobs();
        $this->deliver($this->sylius->pay('77'));
        $record = OrderEventRecord::query()->firstOrFail();

        $this->expectException(UnlinkedProduct::class);
        (new ApplyOrderEvent($record->id))->handle(app(\Ulams\Commerce\Services\OrderEventApplier::class));
    }

    public function testApplyingTheSameOrderTwiceIsANoOp(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $this->deliver($this->sylius->pay('77'));
        $first = Entitlement::query()->firstOrFail();

        // the provider sends a second "paid" event for the same state (sequence 2, new id)
        $again = ['id' => 'second', 'sequence' => 2] + $this->sylius->pay('77');
        $this->deliver($again);

        $this->assertSame(1, Entitlement::query()->count());
        $this->assertSame($first->id, Entitlement::query()->firstOrFail()->id);
        $this->assertSame(1, CourseUserPivot::query()->where('course_id', $course->getKey())->count());
    }

    public function testARepurchaseAfterARefundRestoresAccess(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $this->deliver($this->sylius->pay('77'));
        $this->deliver($this->sylius->refund('77'));
        $this->assertFalse($this->hasAccess($user, $course));

        $this->sylius->placeOrder('78', $user->getKey(), ['1001' => 'variant-course-1']);
        $this->deliver($this->sylius->pay('78'));

        $this->assertTrue($this->hasAccess($user, $course));
    }

    // ---- reconciliation (ADR 0130, section 5.5) -------------------------------------------------

    public function testAMissedWebhookIsRecoveredByReconciliation(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $this->sylius->pay('77'); // the webhook URL was blocked: nothing is ever delivered
        $this->assertFalse($this->hasAccess($user, $course));

        $this->artisan('ulams:commerce:reconcile')->expectsOutputToContain('1 missed event(s) queued')->assertSuccessful();

        $this->assertTrue($this->hasAccess($user, $course));
        $this->assertDatabaseHas('commerce_order_events', ['order_id' => '77', 'source' => 'reconcile', 'outcome' => 'applied']);
        $this->assertSame('1', SyncState::query()->where('provider', 'fake')->where('channel', 'acme')->firstOrFail()->cursor);
    }

    public function testAMissedRefundIsRecoveredToo(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $this->deliver($this->sylius->pay('77'));
        $this->sylius->refund('77'); // lost

        Artisan::call('ulams:commerce:reconcile');

        $this->assertFalse($this->hasAccess($user, $course));
    }

    public function testReconciliationSkipsEventsThatWereAlreadyDeliveredAndResumesFromItsCursor(): void
    {
        $user = $this->user();
        $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $this->deliver($this->sylius->pay('77'));
        $fetches = $this->sylius->fetches;

        $this->artisan('ulams:commerce:reconcile')->expectsOutputToContain('0 missed event(s) queued')->assertSuccessful();
        $this->artisan('ulams:commerce:reconcile')->assertSuccessful();

        $this->assertSame($fetches, $this->sylius->fetches);
        $this->assertSame(1, DB::table('commerce_order_events')->count());
        $this->sylius->placeOrder('78', $user->getKey(), ['1001' => 'variant-course-1']);
        $this->sylius->pay('78');
        $this->artisan('ulams:commerce:reconcile')->expectsOutputToContain('1 missed event(s) queued')->assertSuccessful();
    }

    public function testReconciliationQueuesEventsThatWereStoredButNeverApplied(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $this->loseQueuedJobs();
        $this->deliver($this->sylius->pay('77')); // stored, the worker died before running the job
        $this->assertFalse($this->hasAccess($user, $course));
        OrderEventRecord::query()->update(['received_at' => now()->subMinutes(10)]);
        $this->restoreQueue();

        $this->artisan('ulams:commerce:reconcile')->expectsOutputToContain('1 stuck event(s) queued again')->assertSuccessful();

        $this->assertTrue($this->hasAccess($user, $course));
    }

    public function testRetryFailedRequeuesEventsTheJobGaveUpOn(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1']);
        $this->sylius->down = true;
        $this->loseQueuedJobs();
        $this->deliver($this->sylius->pay('77'));
        $record = OrderEventRecord::query()->firstOrFail();
        $record->forceFill(['outcome' => 'failed', 'received_at' => now()->subHour()])->save();
        $this->sylius->down = false;
        $this->restoreQueue();

        $this->artisan('ulams:commerce:reconcile')->assertSuccessful();
        $this->assertFalse($this->hasAccess($user, $course), 'failed events are not retried by a plain run');
        $this->artisan('ulams:commerce:reconcile', ['--retry-failed' => true])->assertSuccessful();

        $this->assertTrue($this->hasAccess($user, $course));
    }

    public function testReconciliationIgnoresFeedEventsOfAnotherChannel(): void
    {
        $user = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $user->getKey(), ['991' => 'variant-course-1'], 'globex');
        $this->sylius->pay('77');

        $this->artisan('ulams:commerce:reconcile')->expectsOutputToContain('another channel')->assertSuccessful();

        $this->assertFalse($this->hasAccess($user, $course));
        $this->assertSame(0, DB::table('commerce_order_events')->count());
    }

    public function testReconciliationNeedsAChannelAndAKnownProviderWithAFeed(): void
    {
        config(['commerce.channel' => null]);
        $this->artisan('ulams:commerce:reconcile')->assertFailed();
        $this->artisan('ulams:commerce:reconcile', ['--provider' => 'nope'])->assertFailed();
        config(['commerce.channel' => 'acme']);
        $this->artisan('ulams:commerce:reconcile', ['--provider' => 'wellms'])->expectsOutputToContain('no event feed')->assertSuccessful();
    }

    public function testEntitlementsOfDifferentUsersAreIndependent(): void
    {
        $alice = $this->user();
        $bob = $this->user();
        $course = $this->course();
        $this->sylius->placeOrder('77', $alice->getKey(), ['991' => 'variant-course-1']);
        $this->sylius->placeOrder('78', $bob->getKey(), ['992' => 'variant-course-1']);
        $this->deliver($this->sylius->pay('77'));
        $this->deliver($this->sylius->pay('78'));

        $this->deliver($this->sylius->refund('77'));

        $this->assertFalse($this->hasAccess($alice, $course));
        $this->assertTrue($this->hasAccess($bob, $course));
        $this->assertTrue(app(EntitlementService::class)->hasAccess($bob->getKey(), $course->getKey()));
    }
}
