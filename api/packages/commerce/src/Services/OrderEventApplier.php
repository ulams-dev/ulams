<?php

namespace Ulams\Commerce\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Ulams\Commerce\CommerceManager;
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
 * - paid (or partially refunded): one entitlement per line, keyed `<provider>:<channel>:order_item:<id>`; a bundle or an
 *   access pass grants one per course (`...:course:<id>`), a pass for its number of days;
 * - refunded or cancelled: every entitlement of the order is revoked; a line whose units were all refunded loses
 *   its entitlement; a goodwill amount refund keeps access;
 * - anything else (a forged or premature event): nothing changes and the event is `rejected`.
 *
 * An event with a sequence not above the order's last applied one is `stale`: the re-fetch of a newer event
 * already read the final state. Entitlements reach `course_user` only through `EntitlementService`.
 *
 * The nightly sweep uses the same rules on an order it listed itself: `sweepOrder()`.
 */
class OrderEventApplier
{
    public function __construct(
        private readonly CommerceManager $commerce,
        private readonly EntitlementService $entitlements,
        private readonly SellableCatalogue $catalogue,
    ) {
    }

    public function apply(int $recordId): void
    {
        DB::transaction(function () use ($recordId): void {
            $record = OrderEventRecord::query()->lockForUpdate()->findOrFail($recordId);
            if ($record->outcome !== null) {
                return; // applied by another job, or queued twice
            }
            $order = $this->projection($record->provider, $record->order_id);
            if ($record->sequence <= $order->last_sequence) {
                $this->finish($record, OrderEventRecord::OUTCOME_STALE);

                return;
            }

            $snapshot = $this->commerce->provider($record->provider)->fetchOrder($record->order_id);
            $channel = (string) config('commerce.channel');
            if ($snapshot->orderId !== $record->order_id || $channel === '' || $snapshot->channel !== $channel) {
                $this->finish($record, OrderEventRecord::OUTCOME_REJECTED, 'The order does not belong to this tenant channel.');

                return;
            }

            $problem = $this->converge($record->provider, $snapshot, $channel, true)['problem'];
            if ($problem !== null) {
                $this->finish($record, OrderEventRecord::OUTCOME_REJECTED, $problem);

                return;
            }
            $this->project($order, $snapshot, $record->sequence);
            $this->finish($record, OrderEventRecord::OUTCOME_APPLIED);
        });
    }

    /**
     * One order of the nightly sweep: compares the entitlements with the order the provider reports, and with `$repair`
     * puts them right. Returns what was (or would be) out of step: `grant <key>` for a paid line without an entitlement,
     * `revoke <key>` for a refunded or cancelled line that still grants access, or `unlinked`/`unpaid` problems.
     *
     * @return list<string>
     */
    public function sweepOrder(string $provider, OrderSnapshot $snapshot, bool $repair): array
    {
        $channel = (string) config('commerce.channel');
        if ($channel === '' || $snapshot->channel !== $channel) {
            return [];
        }
        $settled = in_array($snapshot->paymentState, [OrderSnapshot::PAYMENT_PAID, OrderSnapshot::PAYMENT_PARTIALLY_REFUNDED, OrderSnapshot::PAYMENT_REFUNDED], true);
        if (!$settled && $snapshot->state !== OrderSnapshot::STATE_CANCELLED) {
            return []; // not paid yet: there is nothing to compare, and an unpaid order is not drift
        }

        return DB::transaction(function () use ($provider, $snapshot, $channel, $repair): array {
            $order = $this->projection($provider, $snapshot->orderId);
            $result = $this->converge($provider, $snapshot, $channel, $repair);
            if ($result['problem'] !== null) {
                return ["problem: {$result['problem']}"];
            }
            if ($repair && $result['changes'] !== []) {
                $this->project($order, $snapshot, $order->last_sequence);
            }

            return $result['changes'];
        });
    }

    private function projection(string $provider, string $orderId): OrderProjection
    {
        DB::table('commerce_orders')->insertOrIgnore([
            'provider' => $provider, 'external_order_id' => $orderId, 'last_sequence' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return OrderProjection::query()->where('provider', $provider)->where('external_order_id', $orderId)->lockForUpdate()->firstOrFail();
    }

    private function project(OrderProjection $order, OrderSnapshot $snapshot, int $sequence): void
    {
        $order->forceFill([
            'number' => $snapshot->number, 'token' => $snapshot->token, 'user_id' => $snapshot->userRef,
            'state' => $snapshot->state, 'payment_state' => $snapshot->paymentState, 'currency' => $snapshot->currency,
            'total_minor' => $snapshot->totalMinor, 'tax_minor' => $snapshot->taxMinor,
            'last_sequence' => $sequence, 'synced_at' => now(),
        ])->save();
    }

    /**
     * Makes entitlements match the order, or (without `$apply`) only reports what would change.
     *
     * @return array{problem:?string,changes:list<string>} why the order was refused, and the grants/revokes that changed state
     */
    private function converge(string $provider, OrderSnapshot $order, string $channel, bool $apply): array
    {
        $key = fn (string $itemId) => "{$provider}:{$channel}:order_item:{$itemId}";
        $revokeAll = $order->state === OrderSnapshot::STATE_CANCELLED || $order->paymentState === OrderSnapshot::PAYMENT_REFUNDED;
        if (!$revokeAll && !in_array($order->paymentState, [OrderSnapshot::PAYMENT_PAID, OrderSnapshot::PAYMENT_PARTIALLY_REFUNDED], true)) {
            return ['problem' => "The provider does not report the order as paid (payment state \"{$order->paymentState}\").", 'changes' => []];
        }
        if (!$revokeAll && ($order->userRef === null || !User::query()->whereKey($order->userRef)->exists())) {
            return ['problem' => 'The order customer is not linked to an LMS user.', 'changes' => []];
        }
        $reason = $order->state === OrderSnapshot::STATE_CANCELLED ? 'cancelled' : 'refund';

        // one entry per (order line, course): a bundle expands to a key per course, an access pass sets the end date
        $lines = [];
        foreach ($order->items as $item) {
            $link = ProductLink::query()->where('provider', $provider)
                ->where(fn ($q) => $q->where('external_id', $item->productRef)->orWhere('variant_code', $item->productRef))->first();
            $courses = $link === null ? [] : $this->coursesOf($link);
            if ($courses === []) {
                if ($revokeAll) {
                    $lines[] = [$key($item->id), null, null, true, null];
                    continue;
                }
                throw new UnlinkedProduct("No LMS course is linked to product \"{$item->productRef}\" of order {$order->orderId}.");
            }
            $until = $link->sellable_type === SellableRef::ACCESS_PASS ? $this->passEnd($link) : null;
            foreach ($courses as $courseId) {
                $lines[] = [$key($item->id) . ($link->sellable_type === SellableRef::COURSE ? '' : ":course:{$courseId}"), $courseId, $until, $revokeAll || $item->refunded, $link];
            }
        }

        $changes = [];
        foreach ($lines as [$sourceKey, $courseId, $until, $revoked, $link]) {
            $existing = Entitlement::query()->where('source_key', $sourceKey)->first();
            if ($revoked) {
                if ($existing !== null && $existing->status !== Entitlement::STATUS_REVOKED) {
                    $changes[] = "revoke {$sourceKey}";
                    $apply && $this->entitlements->revoke($sourceKey, $revokeAll ? $reason : 'refund');
                }
                continue;
            }
            if ($existing === null) {
                $changes[] = "grant {$sourceKey}";
                $apply && $this->entitlements->grantCourse(
                    $order->userRef,
                    $courseId,
                    Entitlement::SOURCE_ORDER_ITEM,
                    $sourceKey,
                    ['provider' => $provider, 'order' => $order->number ?? $order->orderId, 'channel' => $channel, 'sellable' => "{$link->sellable_type}:{$link->sellable_id}"],
                    $until,
                );
            }
        }

        return ['problem' => null, 'changes' => $changes];
    }

    /** @return list<int> the courses a purchased product grants */
    private function coursesOf(ProductLink $link): array
    {
        return match ($link->sellable_type) {
            SellableRef::COURSE => [$link->sellable_id],
            SellableRef::BUNDLE, SellableRef::ACCESS_PASS => $this->catalogue->describe(new SellableRef($link->sellable_type, $link->sellable_id))->courseIds,
            default => [],
        };
    }

    /** An access pass ends `days` after the purchase; the end is fixed when the entitlement is first created. */
    private function passEnd(ProductLink $link): ?CarbonInterface
    {
        $days = $this->catalogue->describe(new SellableRef($link->sellable_type, $link->sellable_id))->accessDays;

        return $days === null ? null : now()->addDays($days);
    }

    private function finish(OrderEventRecord $record, string $outcome, ?string $error = null): void
    {
        $record->forceFill(['outcome' => $outcome, 'error' => $error, 'applied_at' => now()])->save();
    }
}
