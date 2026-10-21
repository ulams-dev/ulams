<?php

namespace Ulams\Entitlements\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
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
     */
    public function grantCourse(
        int $userId,
        int $courseId,
        string $sourceType,
        string $sourceKey,
        array $sourceRef = [],
        ?CarbonInterface $validUntil = null,
        ?string $grantedBy = null,
    ): Entitlement {
        return DB::transaction(function () use ($userId, $courseId, $sourceType, $sourceKey, $sourceRef, $validUntil, $grantedBy) {
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
            $this->project($userId, $courseId);

            return $entitlement;
        });
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
            $this->access->removeAccessForUsers($course, [$userId]);

            return;
        }
        $end = $active->contains(fn (Entitlement $e) => $e->valid_until === null) ? null : $active->max('valid_until');
        $this->access->addAccessForUsers($course, [$userId => ['end_date' => $end]]);
    }

    private function inForce(int $userId, int $courseId)
    {
        return Entitlement::query()->inForce()
            ->where('user_id', $userId)
            ->where('subject_type', Entitlement::SUBJECT_COURSE)
            ->where('subject_id', $courseId);
    }

    /**
     * Access that exists without an entitlement (an admin attached the course) is recorded as a `migration`
     * entitlement before the first purchase, so refunding the purchase can never remove it. The full backfill
     * and a `CourseAccessService` decorator follow in CM-2.
     */
    private function adoptExistingAccess(int $userId, int $courseId): void
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
