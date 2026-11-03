<?php

namespace Ulams\CourseVariants\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Ulams\Auth\Support\GroupTree;
use Ulams\Courses\Models\CourseGroupPivot;
use Ulams\CourseVariants\Models\VariantGroup;
use Ulams\Entitlements\Models\Entitlement;
use Ulams\Entitlements\Services\EntitlementService;

/**
 * One entitlement covers the whole family (ADR 0192, 0130): a learner who holds an entitlement for any course of the
 * family (bought, assigned, an LTI launch, a group) may open every variant. The LMS owns this decision; the commerce
 * layer only reports orders. The variant courses a learner actually uses get a derived `family` entitlement, created
 * when the learner picks or is assigned the variant, so `course_user` stays a projection of entitlements and the nightly
 * parity check holds. A derived entitlement ends with the ones it derives from.
 */
final class FamilyAccess
{
    /** @var array<string,bool> re-entrancy guard: a derived grant projects access, which fires the listener again */
    private static array $busy = [];

    public function __construct(private readonly EntitlementService $entitlements)
    {
    }

    /**
     * Whether the user holds access to the family on its own account: an entitlement in force that is not derived, or
     * membership of a group the family's courses are open to.
     *
     * @return array{covered:bool,end:?CarbonInterface} `end` is null when open-ended
     */
    public function coverage(int $userId, VariantGroup $group): array
    {
        $courseIds = $group->courseIds();
        $rows = Entitlement::query()->inForce()->where('user_id', $userId)
            ->where('subject_type', Entitlement::SUBJECT_COURSE)->whereIn('subject_id', $courseIds)
            ->where('source_type', '!=', Entitlement::SOURCE_FAMILY)->get(['valid_until']);
        if ($this->groupCovers($userId, $courseIds)) {
            return ['covered' => true, 'end' => null];
        }
        if ($rows->isEmpty()) {
            return ['covered' => false, 'end' => null];
        }

        return ['covered' => true, 'end' => $rows->contains(fn ($r) => $r->valid_until === null) ? null : $rows->max('valid_until')];
    }

    public function covers(int $userId, VariantGroup $group): bool
    {
        return $this->coverage($userId, $group)['covered'];
    }

    /**
     * Makes sure the user can open `$courseId` of the family. Returns false when the family does not cover the user.
     * A course the user already holds access to is left alone.
     */
    public function enrol(int $userId, VariantGroup $group, int $courseId): bool
    {
        ['covered' => $covered, 'end' => $end] = $this->coverage($userId, $group);
        if (!$covered) {
            return false;
        }
        if ($this->holdsOwnAccess($userId, $courseId)) {
            return true;
        }
        $this->guarded($userId, $group, fn () => $this->entitlements->syncDerived($userId, $courseId, Entitlement::SOURCE_FAMILY, $this->key($group, $userId, $courseId), ['group_id' => $group->id], $end));

        return true;
    }

    /**
     * Brings the user's derived entitlements in step with what the family covers: ended when nothing covers the user
     * any more, moved to the new end otherwise.
     */
    public function refresh(int $userId, VariantGroup $group): void
    {
        $derived = Entitlement::query()->where('user_id', $userId)->where('source_type', Entitlement::SOURCE_FAMILY)
            ->where('source_key', 'like', "family:{$group->id}:user:{$userId}:course:%")->where('status', Entitlement::STATUS_ACTIVE)->get();
        if ($derived->isEmpty()) {
            return;
        }
        ['covered' => $covered, 'end' => $end] = $this->coverage($userId, $group);
        $this->guarded($userId, $group, function () use ($derived, $covered, $end, $group, $userId): void {
            foreach ($derived as $row) {
                if (!$covered) {
                    $this->entitlements->revoke($row->source_key, 'family_lost');
                } elseif (($row->valid_until?->getTimestamp()) !== $end?->getTimestamp()) {
                    $this->entitlements->syncDerived($userId, (int) $row->subject_id, Entitlement::SOURCE_FAMILY, $row->source_key, ['group_id' => $group->id], $end);
                }
            }
        });
    }

    public function busy(int $userId, VariantGroup $group): bool
    {
        return isset(self::$busy["{$userId}:{$group->id}"]);
    }

    private function holdsOwnAccess(int $userId, int $courseId): bool
    {
        return Entitlement::query()->inForce()->where('user_id', $userId)->where('subject_type', Entitlement::SUBJECT_COURSE)
            ->where('subject_id', $courseId)->where('source_type', '!=', Entitlement::SOURCE_FAMILY)->exists();
    }

    private function key(VariantGroup $group, int $userId, int $courseId): string
    {
        return "family:{$group->id}:user:{$userId}:course:{$courseId}";
    }

    private function guarded(int $userId, VariantGroup $group, callable $work): void
    {
        $key = "{$userId}:{$group->id}";
        $was = self::$busy[$key] ?? false;
        self::$busy[$key] = true;
        try {
            $work();
        } finally {
            if (!$was) {
                unset(self::$busy[$key]);
            }
        }
    }

    /** @param int[] $courseIds */
    private function groupCovers(int $userId, array $courseIds): bool
    {
        $groupIds = CourseGroupPivot::query()->whereIn('course_id', $courseIds)->pluck('group_id')->unique()->all();
        if ($groupIds === []) {
            return false;
        }
        $all = array_merge($groupIds, GroupTree::descendantsOfMany($groupIds));

        return DB::table('group_user')->whereIn('group_id', $all)->where('user_id', $userId)->exists();
    }
}
