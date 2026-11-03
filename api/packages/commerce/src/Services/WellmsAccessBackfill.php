<?php

namespace Ulams\Commerce\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ulams\Cart\Enums\OrderStatus;
use Ulams\Cart\Models\Product;
use Ulams\Courses\Models\Course;
use Ulams\Entitlements\Models\Entitlement;
use Ulams\Entitlements\Models\ParityReport;
use Ulams\Entitlements\Support\AccessParity;

/**
 * Gives every existing `course_user` row an entitlement (ADR 0130, section 6.3) without changing any access:
 *
 * 1. snapshot hashes of `course_user` and `products_users`;
 * 2. each `course_user` row with no entitlement in force gets one that mirrors it (same end date): source
 *    `wellms_order` when a paid order line of a product that contains the course explains it (key
 *    `wellms:order_item:<line>:course:<course>`), `migration` otherwise (key `migration:course_user:<course>:<user>`);
 * 3. each Wellms subscription (`products_users.end_date`) over a course the user has gets an entitlement ending
 *    with the subscription, never later than the row itself;
 * 4. the parity check runs inside the same transaction; one difference, or a dry run, rolls everything back.
 *
 * Idempotent by `source_key`. The report is stored after the transaction so a failed or dry run still leaves its
 * evidence. Only writes the `entitlements` table, never `course_user`: the second snapshot proves it.
 */
class WellmsAccessBackfill
{
    private const CHUNK = 500;

    public function __construct(private readonly AccessParity $parity)
    {
    }

    public function run(bool $dryRun = false): ParityReport
    {
        $before = $this->parity->snapshot();
        $created = [Entitlement::SOURCE_WELLMS_ORDER => 0, Entitlement::SOURCE_MIGRATION => 0];
        $skippedSubscriptions = 0;

        DB::beginTransaction();
        try {
            $explained = $this->orderLinesExplainingCourseAccess();
            DB::table('course_user')->select('id', 'user_id', 'course_id', 'end_date', 'created_at')->orderBy('id')
                ->chunkById(self::CHUNK, function ($rows) use ($explained, &$created): void {
                    $this->backfillRows($rows->all(), $explained, $created);
                });
            $subscriptionEntitlements = $this->backfillSubscriptions($skippedSubscriptions);

            $after = $this->parity->snapshot();
            $result = $this->parity->check((int) config('entitlements.parity.sample'));
            $unchanged = $before === $after;
            $passed = $result->passed() && $unchanged;
            $total = array_sum($created) + $subscriptionEntitlements;
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
        if ($dryRun || !$passed) {
            DB::rollBack();
        } else {
            DB::commit();
        }

        return ParityReport::query()->create([
            'kind' => $dryRun ? ParityReport::KIND_DRY_RUN : ParityReport::KIND_BACKFILL,
            'status' => $passed ? ParityReport::STATUS_PASSED : ParityReport::STATUS_FAILED,
            'pairs_checked' => $result->pairs,
            'differences' => $result->differences + ($unchanged ? 0 : 1),
            'entitlements_created' => $dryRun || !$passed ? 0 : $total,
            'details' => $result->details() + [
                'would_create' => ['course_user_rows' => $created, 'subscriptions' => $subscriptionEntitlements],
                'subscriptions_without_course_row' => $skippedSubscriptions,
                'access_changed' => !$unchanged,
                'rolled_back' => $dryRun || !$passed,
            ],
            'snapshot_before' => $before['course_user'],
            'snapshot_after' => $after['course_user'],
        ]);
    }

    /**
     * (user, course) => id of the earliest paid order line that explains the pair.
     *
     * @return array<string,int>
     */
    private function orderLinesExplainingCourseAccess(): array
    {
        $map = [];
        $query = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->join('products_productables as pp', fn ($j) => $j->on('pp.product_id', '=', 'order_items.buyable_id')->where('pp.productable_type', $this->courseType()))
            ->where('order_items.buyable_type', (new Product())->getMorphClass())
            ->whereIn('orders.status', [OrderStatus::PAID, OrderStatus::TRIAL_PAID])
            ->whereNotNull('orders.user_id')
            ->selectRaw('orders.user_id, pp.productable_id AS course_id, MIN(order_items.id) AS line_id')
            ->groupBy('orders.user_id', 'pp.productable_id');
        foreach ($query->cursor() as $row) {
            $map[$row->user_id . ':' . $row->course_id] = (int) $row->line_id;
        }

        return $map;
    }

    /**
     * @param list<object> $rows
     * @param array<string,int> $explained
     * @param array<string,int> $created
     */
    private function backfillRows(array $rows, array $explained, array &$created): void
    {
        $userIds = array_unique(array_map(fn ($r) => (int) $r->user_id, $rows));
        $inForce = Entitlement::query()->inForce()->whereIn('user_id', $userIds)->where('subject_type', Entitlement::SUBJECT_COURSE)
            ->get(['user_id', 'subject_id'])->mapWithKeys(fn ($e) => [$e->user_id . ':' . $e->subject_id => true])->all();

        $candidates = [];
        foreach ($rows as $row) {
            $pair = $row->user_id . ':' . $row->course_id;
            if (isset($inForce[$pair])) {
                continue; // something already explains it (a purchase after CM-1, an adopted row)
            }
            $line = $explained[$pair] ?? null;
            $type = $line !== null ? Entitlement::SOURCE_WELLMS_ORDER : Entitlement::SOURCE_MIGRATION;
            $key = $line !== null ? "wellms:order_item:{$line}:course:{$row->course_id}" : "migration:course_user:{$row->course_id}:{$row->user_id}";
            $end = $row->end_date === null ? null : Carbon::parse($row->end_date);
            $candidates[$key] = [
                'type' => $type,
                'row' => [
                    'id' => Str::lower((string) Str::ulid()),
                    'user_id' => $row->user_id,
                    'subject_type' => Entitlement::SUBJECT_COURSE,
                    'subject_id' => $row->course_id,
                    'status' => $end !== null && $end->isPast() ? Entitlement::STATUS_EXPIRED : Entitlement::STATUS_ACTIVE,
                    'valid_from' => $row->created_at,
                    'valid_until' => $end,
                    'source_type' => $type,
                    'source_key' => $key,
                    'source_ref' => json_encode(['backfill' => true, 'course_user_id' => $row->id] + ($line !== null ? ['provider' => 'wellms', 'order_item_id' => $line] : [])),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ];
        }
        $existing = $candidates === [] ? [] : Entitlement::query()->whereIn('source_key', array_keys($candidates))->pluck('source_key')->flip()->all();
        $insert = [];
        foreach ($candidates as $key => $candidate) {
            if (!isset($existing[$key])) {
                $insert[] = $candidate['row'];
                $created[$candidate['type']]++;
            }
        }
        if ($insert !== []) {
            Entitlement::query()->insert($insert);
        }
    }

    /**
     * Subscriptions (`products_users.end_date`) over a course the user has: an entitlement ending with the subscription.
     * Subscriptions whose course the user does not have (no `course_user` row) are counted, not invented.
     *
     * @return int entitlements created
     */
    private function backfillSubscriptions(int &$withoutCourseRow): int
    {
        $created = 0;
        DB::table('products_users')->whereNotNull('end_date')->select('id', 'user_id', 'product_id', 'end_date', 'status')->orderBy('id')
            ->chunkById(self::CHUNK, function ($subscriptions) use (&$created, &$withoutCourseRow): void {
                foreach ($subscriptions as $sub) {
                    $courseIds = DB::table('products_productables')->where('product_id', $sub->product_id)
                        ->where('productable_type', $this->courseType())->pluck('productable_id');
                    foreach ($courseIds as $courseId) {
                        $row = DB::table('course_user')->where('user_id', $sub->user_id)->where('course_id', $courseId)->first();
                        if ($row === null) {
                            $withoutCourseRow++;
                            continue;
                        }
                        $key = "wellms:product_user:{$sub->id}:course:{$courseId}";
                        if (Entitlement::query()->where('source_key', $key)->exists()) {
                            continue;
                        }
                        $end = Carbon::parse($sub->end_date);
                        if ($row->end_date !== null && Carbon::parse($row->end_date)->lt($end)) {
                            $end = Carbon::parse($row->end_date); // never longer than the access the learner has today
                        }
                        Entitlement::query()->insert([
                            'id' => Str::lower((string) Str::ulid()),
                            'user_id' => $sub->user_id,
                            'subject_type' => Entitlement::SUBJECT_COURSE,
                            'subject_id' => $courseId,
                            'status' => $end->isPast() ? Entitlement::STATUS_EXPIRED : Entitlement::STATUS_ACTIVE,
                            'valid_from' => $row->created_at,
                            'valid_until' => $end,
                            'source_type' => Entitlement::SOURCE_WELLMS_ORDER,
                            'source_key' => $key,
                            'source_ref' => json_encode(['backfill' => true, 'provider' => 'wellms', 'subscription' => true, 'product_id' => $sub->product_id, 'status' => $sub->status]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $created++;
                    }
                }
            }, 'id');

        return $created;
    }

    private function courseType(): string
    {
        return (new Course())->getMorphClass();
    }
}
