<?php

namespace Ulams\Commerce\Services;

use Illuminate\Support\Facades\DB;
use Ulams\Commerce\CommerceManager;
use Ulams\Commerce\Contracts\FetchesOrders;
use Ulams\Commerce\Models\OrderEventRecord;
use Ulams\Commerce\Models\OrderProjection;
use Ulams\Commerce\Models\ProductLink;
use Ulams\Commerce\Support\OrderSnapshot;
use Ulams\Commerce\Support\SellableRef;
use Ulams\Core\Models\User;
use Ulams\Entitlements\Models\Entitlement;
use Ulams\Entitlements\Services\EntitlementService;

/**
 * Applies one stored order event (ADR 0130, section 5.3). Under a row lock on the order projection it reads the
 * order back from the provider and acts on the provider's current state, never on the webhook payload:
 *
 * - paid (or partially refunded): one entitlement per line, keyed `<provider>:<channel>:order_item:<id>`;
 * - refunded or cancelled: every entitlement of the order is revoked; a line whose units were all refunded loses
 *   its entitlement; a goodwill amount refund keeps access;
 * - anything else (a forged or premature event): nothing changes and the event is `rejected`.
 *
 * An event with a sequence not above the order's last applied one is `stale`: the re-fetch of a newer event
 * already read the final state. Entitlements reach `course_user` only through `EntitlementService`.
 */
class OrderEventApplier
{
    public function __construct(private readonly CommerceManager $commerce, private readonly EntitlementService $entitlements)
    {
    }

    public function apply(int $recordId): void
    {
        DB::transaction(function () use ($recordId): void {
            $record = OrderEventRecord::query()->lockForUpdate()->findOrFail($recordId);
            if ($record->outcome !== null) {
                return; // applied by another job, or queued twice
            }
            DB::table('commerce_orders')->insertOrIgnore([
                'provider' => $record->provider, 'external_order_id' => $record->order_id, 'last_sequence' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $order = OrderProjection::query()->where('provider', $record->provider)->where('external_order_id', $record->order_id)->lockForUpdate()->firstOrFail();
            if ($record->sequence <= $order->last_sequence) {
                $this->finish($record, OrderEventRecord::OUTCOME_STALE);

                return;
            }

            $provider = $this->commerce->provider($record->provider);
            if (!$provider instanceof FetchesOrders) {
                $this->finish($record, OrderEventRecord::OUTCOME_REJECTED, 'The provider cannot be asked for orders.');

                return;
            }
            $snapshot = $provider->fetchOrder($record->order_id);
            $channel = (string) config('commerce.channel');
            if ($snapshot->orderId !== $record->order_id || $channel === '' || $snapshot->channel !== $channel) {
                $this->finish($record, OrderEventRecord::OUTCOME_REJECTED, 'The order does not belong to this tenant channel.');

                return;
            }

            $problem = $this->reconcileEntitlements($record, $snapshot, $channel);
            if ($problem !== null) {
                $this->finish($record, OrderEventRecord::OUTCOME_REJECTED, $problem);

                return;
            }
            $order->forceFill([
                'number' => $snapshot->number, 'token' => $snapshot->token, 'user_id' => $snapshot->userRef,
                'state' => $snapshot->state, 'payment_state' => $snapshot->paymentState, 'currency' => $snapshot->currency,
                'total_minor' => $snapshot->totalMinor, 'tax_minor' => $snapshot->taxMinor,
                'last_sequence' => $record->sequence, 'synced_at' => now(),
            ])->save();
            $this->finish($record, OrderEventRecord::OUTCOME_APPLIED);
        });
    }

    /** Returns why the event was refused, or null when entitlements now match the order. */
    private function reconcileEntitlements(OrderEventRecord $record, OrderSnapshot $order, string $channel): ?string
    {
        $key = fn (string $itemId) => "{$record->provider}:{$channel}:order_item:{$itemId}";
        $revokeAll = $order->state === OrderSnapshot::STATE_CANCELLED || $order->paymentState === OrderSnapshot::PAYMENT_REFUNDED;
        if ($revokeAll) {
            $reason = $order->state === OrderSnapshot::STATE_CANCELLED ? 'cancelled' : 'refund';
            foreach ($order->items as $item) {
                $this->entitlements->revoke($key($item->id), $reason);
            }

            return null;
        }
        if (!in_array($order->paymentState, [OrderSnapshot::PAYMENT_PAID, OrderSnapshot::PAYMENT_PARTIALLY_REFUNDED], true)) {
            return "The provider does not report the order as paid (payment state \"{$order->paymentState}\").";
        }
        if ($order->userRef === null || !User::query()->whereKey($order->userRef)->exists()) {
            return 'The order customer is not linked to an LMS user.';
        }

        $courses = [];
        foreach ($order->items as $item) {
            if ($item->refunded) {
                $this->entitlements->revoke($key($item->id), 'refund');
                continue;
            }
            $link = ProductLink::query()->where('provider', $record->provider)->where('external_id', $item->productRef)->first();
            if ($link === null || $link->sellable_type !== SellableRef::COURSE) {
                // bundles expand to courses in a later milestone; until then they are not granted silently
                throw new UnlinkedProduct("No LMS course is linked to product \"{$item->productRef}\" of order {$record->order_id}.");
            }
            $courses[$item->id] = $link->sellable_id;
        }
        foreach ($courses as $itemId => $courseId) {
            $this->entitlements->grantCourse(
                $order->userRef,
                $courseId,
                Entitlement::SOURCE_ORDER_ITEM,
                $key((string) $itemId),
                ['provider' => $record->provider, 'order' => $order->number ?? $record->order_id, 'channel' => $channel],
            );
        }

        return null;
    }

    private function finish(OrderEventRecord $record, string $outcome, ?string $error = null): void
    {
        $record->forceFill(['outcome' => $outcome, 'error' => $error, 'applied_at' => now()])->save();
    }
}
