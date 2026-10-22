<?php

namespace Ulams\Entitlements\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Ulams\CourseAccess\Models\Course;
use Ulams\CourseAccess\Services\Contracts\CourseAccessServiceContract;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Entitlements\Models\Entitlement;

/**
 * The only writer of purchased access (ADR 0130). Grants and revocations are keyed by `source_key`, so applying
 * the same order twice is a no-op, and they reach `course_user` through `CourseAccessService`, never by writing
 * the table directly. Revoking one entitlement removes access only when no other entitlement still grants it.
 * Progress is never touched (ADR 0033): a re-purchase restores access with progress intact.
 */
class EntitlementService
{
    public function __construct(private readonly CourseAccessServiceContract $access)
    {
    }

    /**
     * Grants a user access to a course for one source. Idempotent: an existing entitlement with the same
     * `$sourceKey` is returned unchanged (also when it was revoked: a revoked source stays revoked).
     *
     * @param array<string,mixed> $sourceRef provider, order number, actor ... (free-form, stored as JSON)
     * @param bool $project false only records the entitlement and leaves `course_user` to another writer (the Wellms
     *                      cart in shadow mode, which attaches the course itself)
     */
    public function grantCourse(
        int $userId,
        int $courseId,
        string $sourceType,
        string $sourceKey,
        array $sourceRef = [],
        ?CarbonInterface $validUntil = null,
        ?string $grantedBy = null,
        bool $project = true,
    ): Entitlement {
        $this->assertSourceType($sourceType);

        return DB::transaction(function () use ($userId, $courseId, $sourceType, $sourceKey, $sourceRef, $validUntil, $grantedBy, $project) {
            $existing = Entitlement::query()->where('source_key', $sourceKey)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }
            Course::query()->findOrFail($courseId);
            $this->adoptExistingAccess($userId, $courseId);

            try {
                // a savepoint, so losing a race on the unique key does not abort the surrounding transaction
                $entitlement = DB::transaction(fn () => Entitlement::query()->create([
                    'user_id' => $userId,
                    'subject_type' => Entitlement::SUBJECT_COURSE,
                    'subject_id' => $courseId,
                    'status' => Entitlement::STATUS_ACTIVE,
                    'valid_from' => now(),
                    'valid_until' => $validUntil,
                    'source_type' => $sourceType,
                    'source_key' => $sourceKey,
                    'source_ref' => $sourceRef,
                    'granted_by' => $grantedBy,
                ]));
            } catch (UniqueConstraintViolationException) {
                return Entitlement::query()->where('source_key', $sourceKey)->firstOrFail();
            }
            if ($project) {
                $this->project($userId, $courseId);
            }

            return $entitlement;
        });
    }

    /**
     * Records access that a person or process attached through `CourseAccessService` (`admin`, `compliance`, `lti`).
     * One in-force entitlement per source type and user/course: attaching again only moves its end date, so the
     * table does not fill with a row per click ($updateEnd false leaves the end alone). A revoked or expired one is replaced by a new row. Does not write
     * `course_user`; call `project()` afterwards.
     */
    public function grantManual(int $userId, int $courseId, string $sourceType, ?CarbonInterface $validUntil = null, array $sourceRef = [], ?string $grantedBy = null, bool $updateEnd = true): Entitlement
    {
        if (!in_array($sourceType, Entitlement::MANUAL_SOURCES, true)) {
            throw new InvalidArgumentException("\"{$sourceType}\" is not a manual entitlement source.");
        }

        return DB::transaction(function () use ($userId, $courseId, $sourceType, $validUntil, $sourceRef, $grantedBy, $updateEnd) {
            $existing = $this->inForce($userId, $courseId)->where('source_type', $sourceType)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($updateEnd) {
                    $existing->forceFill(['valid_until' => $validUntil])->save();
                }

                return $existing;
            }

            return Entitlement::query()->create([
                'user_id' => $userId,
                'subject_type' => Entitlement::SUBJECT_COURSE,
                'subject_id' => $courseId,
                'status' => Entitlement::STATUS_ACTIVE,
                'valid_from' => now(),
                'valid_until' => $validUntil,
                'source_type' => $sourceType,
                'source_key' => "{$sourceType}:course:{$courseId}:user:{$userId}:" . Str::lower((string) Str::ulid()),
                'source_ref' => $sourceRef,
                'granted_by' => $grantedBy,
            ]);
        });
    }

    /**
     * Revokes every active entitlement of the user for the course (an admin removed the user from the course
     * explicitly) and recomputes `course_user`. Returns how many entitlements were revoked.
     */
    public function revokeAllForUser(int $userId, int $courseId, string $reason): int
    {
        return DB::transaction(function () use ($userId, $courseId, $reason) {
            $rows = Entitlement::query()
                ->where('user_id', $userId)->where('subject_type', Entitlement::SUBJECT_COURSE)->where('subject_id', $courseId)
                ->where('status', Entitlement::STATUS_ACTIVE)->lockForUpdate()->get();
            foreach ($rows as $row) {
                $row->forceFill(['status' => Entitlement::STATUS_REVOKED, 'revoked_at' => now(), 'revoke_reason' => $reason])->save();
            }
            if ($rows->isNotEmpty()) {
                $this->project($userId, $courseId);
            }

            return $rows->count();
        });
    }

    /**
     * Marks active entitlements whose end has passed as `expired` and recomputes their `course_user` rows. The row
     * stays (with the end date of the last entitlement) so progress survives and a re-purchase picks it up again.
     * Idempotent; returns the number of entitlements expired.
     */
    public function expireDue(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $expired = 0;
        Entitlement::query()
            ->where('status', Entitlement::STATUS_ACTIVE)->whereNotNull('valid_until')->where('valid_until', '<=', $now)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$expired): void {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row, &$expired): void {
                        $fresh = Entitlement::query()->lockForUpdate()->find($row->getKey());
                        if ($fresh === null || $fresh->status !== Entitlement::STATUS_ACTIVE) {
                            return;
                        }
                        $fresh->forceFill(['status' => Entitlement::STATUS_EXPIRED])->save();
                        $expired++;
                        if ($fresh->user_id !== null && $fresh->subject_type === Entitlement::SUBJECT_COURSE) {
                            $this->project($fresh->user_id, $fresh->subject_id);
                        }
                    });
                }
            });

        return $expired;
    }

    /** Revokes the entitlement of one source. Idempotent; returns null when the source is unknown. */
    public function revoke(string $sourceKey, string $reason): ?Entitlement
    {
        return DB::transaction(function () use ($sourceKey, $reason) {
            $entitlement = Entitlement::query()->where('source_key', $sourceKey)->lockForUpdate()->first();
            if ($entitlement === null || $entitlement->status === Entitlement::STATUS_REVOKED) {
                return $entitlement;
            }
            $entitlement->forceFill(['status' => Entitlement::STATUS_REVOKED, 'revoked_at' => now(), 'revoke_reason' => $reason])->save();
            if ($entitlement->user_id !== null && $entitlement->subject_type === Entitlement::SUBJECT_COURSE) {
                $this->project($entitlement->user_id, $entitlement->subject_id);
            }

            return $entitlement;
        });
    }

    /** True when at least one entitlement of the user for the course is in force. */
    public function hasAccess(int $userId, int $courseId): bool
    {
        return $this->inForce($userId, $courseId)->exists();
    }

    /**
     * Recomputes `course_user` for one user and course from the entitlements in force: the latest end date wins,
     * no end date when any entitlement is open-ended, and the row goes away when nothing grants access anymore.
     */
    public function project(int $userId, int $courseId): void
    {
        $course = Course::query()->find($courseId);
        if ($course === null) {
            return;
        }
        $active = $this->inForce($userId, $courseId)->get();
        if ($active->isEmpty()) {
            $lapsed = Entitlement::query()
                ->where('user_id', $userId)->where('subject_type', Entitlement::SUBJECT_COURSE)->where('subject_id', $courseId)
                ->where(fn ($q) => $q->where('status', Entitlement::STATUS_EXPIRED)
                    ->orWhere(fn ($q) => $q->where('status', Entitlement::STATUS_ACTIVE)->whereNotNull('valid_until')))
                ->max('valid_until');
            if ($lapsed !== null) {
                // access ended by time, not by a revocation: keep the row (progress stays) with the end date that closed it
                if (CourseUserPivot::query()->where('user_id', $userId)->where('course_id', $courseId)->exists()) {
                    $this->access->addAccessForUsers($course, [$userId => ['end_date' => Carbon::parse($lapsed)]]);
                }

                return;
            }
            $this->access->removeAccessForUsers($course, [$userId]);

            return;
        }
        $end = $active->contains(fn (Entitlement $e) => $e->valid_until === null) ? null : $active->max('valid_until');
        $this->access->addAccessForUsers($course, [$userId => ['end_date' => $end]]);
    }

    private function assertSourceType(string $sourceType): void
    {
        if (!in_array($sourceType, Entitlement::SOURCE_TYPES, true)) {
            throw new InvalidArgumentException("Unknown entitlement source type \"{$sourceType}\".");
        }
    }

    private function inForce(int $userId, int $courseId)
    {
        return Entitlement::query()->inForce()
            ->where('user_id', $userId)
            ->where('subject_type', Entitlement::SUBJECT_COURSE)
            ->where('subject_id', $courseId);
    }

    /**
     * Access that exists without an entitlement is recorded as a `migration` entitlement before another source
     * changes it, so refunding a purchase can never remove it. The backfill covers rows nobody touches; this covers
     * the rows that are touched first.
     */
    public function adoptExistingAccess(int $userId, int $courseId): void
    {
        $row = CourseUserPivot::query()->where('user_id', $userId)->where('course_id', $courseId)->first();
        if ($row === null || $this->inForce($userId, $courseId)->exists()) {
            return;
        }
        $key = "migration:course_user:{$courseId}:{$userId}";
        if (Entitlement::query()->where('source_key', $key)->exists()) {
            return;
        }
        Entitlement::query()->create([
            'user_id' => $userId,
            'subject_type' => Entitlement::SUBJECT_COURSE,
            'subject_id' => $courseId,
            'status' => $row->end_date !== null && $row->end_date->isPast() ? Entitlement::STATUS_EXPIRED : Entitlement::STATUS_ACTIVE,
            'valid_from' => $row->created_at,
            'valid_until' => $row->end_date,
            'source_type' => Entitlement::SOURCE_MIGRATION,
            'source_key' => $key,
            'source_ref' => ['adopted_before' => 'first purchase'],
        ]);
    }
}
