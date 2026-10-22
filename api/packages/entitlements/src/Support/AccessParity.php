<?php

namespace Ulams\Entitlements\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ulams\Entitlements\Models\Entitlement;

/**
 * Parity of `course_user` (what policies read) and the entitlements (what granted it), ADR 0130 section 6.3.
 *
 * For every (user, course) pair the access a learner has through `course_user` must equal the access the
 * entitlements in force give: same yes/no, and when yes, the same end date. Access is judged like
 * `Course::hasUser()` does: a row whose `end_date` is today or later is in force. Differences are reported per kind:
 *
 * - `missing_entitlement`: `course_user` grants access, no entitlement explains it (backfill not run, or a code path
 *   wrote the table directly);
 * - `unprojected_entitlement`: an entitlement is in force but `course_user` has no access (the projection is stale);
 * - `end_date_mismatch`: both grant access, with different end dates.
 *
 * Group access (`course_group`) is not part of `course_user` and never compared.
 */
class AccessParity
{
    public const MISSING_ENTITLEMENT = 'missing_entitlement';
    public const UNPROJECTED_ENTITLEMENT = 'unprojected_entitlement';
    public const END_DATE_MISMATCH = 'end_date_mismatch';

    public function check(int $sampleLimit = 100): ParityResult
    {
        $today = Carbon::today()->toDateString();

        // entitlements in force (same date rule as `hasUser`), one row per pair
        $expected = [];
        $rows = Entitlement::query()
            ->selectRaw('user_id, subject_id, SUM(CASE WHEN valid_until IS NULL THEN 1 ELSE 0 END) AS open_ended, MAX(valid_until) AS latest')
            ->where('status', Entitlement::STATUS_ACTIVE)
            ->where('subject_type', Entitlement::SUBJECT_COURSE)
            ->whereNotNull('user_id')
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today))
            ->groupBy('user_id', 'subject_id')
            ->cursor();
        foreach ($rows as $row) {
            $expected[$row->user_id . ':' . $row->subject_id] = (int) $row->open_ended > 0 ? null : Carbon::parse($row->latest);
        }

        $pairs = 0;
        $differences = 0;
        $byKind = [];
        $sample = [];
        $record = function (string $kind, int $user, int $course, ?string $expectedEnd, ?string $actualEnd) use (&$differences, &$byKind, &$sample, $sampleLimit): void {
            $differences++;
            $byKind[$kind] = ($byKind[$kind] ?? 0) + 1;
            if (count($sample) < $sampleLimit) {
                $sample[] = ['user_id' => $user, 'course_id' => $course, 'kind' => $kind, 'expected_end' => $expectedEnd, 'actual_end' => $actualEnd];
            }
        };

        $seen = [];
        DB::table('course_user')->select('id', 'user_id', 'course_id', 'end_date')
            ->lazyById(1000, 'id')
            ->each(function ($row) use (&$expected, &$seen, &$pairs, $today, $record): void {
                $key = $row->user_id . ':' . $row->course_id;
                $actualEnd = $row->end_date === null ? null : Carbon::parse($row->end_date);
                $actual = $actualEnd === null || $actualEnd->toDateString() >= $today;
                $has = array_key_exists($key, $expected);
                if ($has) {
                    $seen[$key] = true;
                }
                if (!$actual && !$has) {
                    return; // no access on either side: a lapsed row is not a difference
                }
                $pairs++;
                if ($actual && !$has) {
                    $record(self::MISSING_ENTITLEMENT, (int) $row->user_id, (int) $row->course_id, null, $actualEnd?->toIso8601String());
                } elseif (!$actual) {
                    $record(self::UNPROJECTED_ENTITLEMENT, (int) $row->user_id, (int) $row->course_id, $expected[$key]?->toIso8601String(), $actualEnd?->toIso8601String());
                } elseif (!$this->sameEnd($expected[$key], $actualEnd)) {
                    $record(self::END_DATE_MISMATCH, (int) $row->user_id, (int) $row->course_id, $expected[$key]?->toIso8601String(), $actualEnd?->toIso8601String());
                }
            });

        foreach ($expected as $key => $end) {
            if (isset($seen[$key])) {
                continue;
            }
            [$user, $course] = array_map('intval', explode(':', $key));
            $pairs++;
            $record(self::UNPROJECTED_ENTITLEMENT, $user, $course, $end?->toIso8601String(), null);
        }

        return new ParityResult($pairs, $differences, $byKind, $sample);
    }

    /**
     * One hash per table over its sorted rows, so a run can prove it changed no access: `course_user` as
     * (user, course, end_date), `products_users` (Wellms purchases) as (user, product, quantity, end_date).
     *
     * @return array{course_user: string, products_users: string|null}
     */
    public function snapshot(): array
    {
        $courseUser = hash_init('sha256');
        DB::table('course_user')->select('user_id', 'course_id', 'end_date')->orderBy('user_id')->orderBy('course_id')
            ->cursor()
            ->each(fn ($r) => hash_update($courseUser, "{$r->user_id}|{$r->course_id}|" . ($r->end_date ? Carbon::parse($r->end_date)->toIso8601String() : '') . "\n"));

        $productsUsers = null;
        if (Schema::hasTable('products_users')) {
            $ctx = hash_init('sha256');
            DB::table('products_users')->select('user_id', 'product_id', 'quantity', 'end_date')->orderBy('user_id')->orderBy('product_id')->orderBy('id')
                ->cursor()
                ->each(fn ($r) => hash_update($ctx, "{$r->user_id}|{$r->product_id}|{$r->quantity}|" . ($r->end_date ? Carbon::parse($r->end_date)->toIso8601String() : '') . "\n"));
            $productsUsers = hash_final($ctx);
        }

        return ['course_user' => hash_final($courseUser), 'products_users' => $productsUsers];
    }

    private function sameEnd(?Carbon $a, ?Carbon $b): bool
    {
        return $a === null || $b === null ? $a === $b : $a->equalTo($b);
    }
}
