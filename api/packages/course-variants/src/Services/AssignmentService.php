<?php

namespace Ulams\CourseVariants\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Ulams\Courses\Models\Course;
use Ulams\CourseVariants\Events\VariantChosen;
use Ulams\CourseVariants\Exceptions\VariantException;
use Ulams\CourseVariants\Models\Assignment;
use Ulams\CourseVariants\Models\Hint;
use Ulams\CourseVariants\Models\VariantGroup;
use Ulams\CourseVariants\Models\VariantGroupMember;
use Ulams\Entitlements\Models\Entitlement;
use Ulams\Entitlements\Services\EntitlementService;

/**
 * Which variant of a family a learner is in (ADR 0192, CV-5): the learner's own choice (also confirmed placement or hint),
 * or the decision of an admin or a rule. A learner is always in one variant at a time; switching keeps the old row as
 * history and the old course's progress, copies completion for identical content and enrols the learner in the new course
 * through the family entitlement. Never silent: every change names its source and, for the learner, its reason.
 */
final class AssignmentService
{
    public function __construct(
        private readonly FamilyAccess $access,
        private readonly ProgressCarryOver $carryOver,
        private readonly EntitlementService $entitlements,
    ) {
    }

    public function current(int $userId, VariantGroup $group): ?Assignment
    {
        return Assignment::query()->current()->where('user_id', $userId)->where('group_id', $group->id)->first();
    }

    /** The courses a new learner may pick: the default course and the members that are not retired and are published. @return int[] */
    public function selectableCourseIds(VariantGroup $group): array
    {
        $ids = [(int) $group->default_course_id, ...$group->members->filter(fn (VariantGroupMember $m) => $m->retired_at === null)->pluck('course_id')->map(fn ($id) => (int) $id)->all()];
        $published = Course::query()->whereIn('id', $ids)->get()->filter(fn (Course $c) => $c->is_published)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_intersect($ids, $published));
    }

    /**
     * The course the learner is in now: the current assignment, else the default course when they can open it, else the
     * first family course they can open, else the default course.
     */
    public function effectiveCourseId(int $userId, VariantGroup $group): int
    {
        $current = $this->current($userId, $group);
        if ($current !== null) {
            return (int) $current->course_id;
        }
        $held = DB::table('course_user')->where('user_id', $userId)->whereIn('course_id', $group->courseIds())->pluck('course_id')->map(fn ($id) => (int) $id)->all();
        if (in_array((int) $group->default_course_id, $held, true) || $held === []) {
            return (int) $group->default_course_id;
        }

        return $held[0];
    }

    /** Whether the learner may change variant now, and when not, why. @return array{allowed:bool,reason:?string} */
    public function mayChange(int $userId, VariantGroup $group): array
    {
        if (!$this->access->covers($userId, $group)) {
            return ['allowed' => false, 'reason' => 'not_enrolled'];
        }
        $current = $this->current($userId, $group);
        if ($group->lock_assigned && $current !== null && $current->byOrganisation()) {
            return ['allowed' => false, 'reason' => 'locked'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Puts a learner in a variant.
     *
     * `$source` self, placement and hint are the learner's own decision (they need the family entitlement and respect the
     * lock); admin and rule are the organisation's and ignore the lock. Only an admin's assignment grants access to a
     * learner who has none; a rule only routes learners who already have it.
     *
     * @return array{assignment:Assignment,carried:int,changed:bool}
     */
    public function choose(int $userId, VariantGroup $group, int $courseId, string $source, ?array $reason = null, ?int $decidedBy = null, ?int $ruleId = null, ?int $hintId = null): array
    {
        if (!in_array($source, Assignment::SOURCES, true)) {
            throw VariantException::invalid('Unknown source.');
        }
        if (!in_array($courseId, $group->courseIds(), true)) {
            throw VariantException::notAVariant();
        }
        $current = $this->current($userId, $group);
        if ($current !== null && (int) $current->course_id === $courseId && !(in_array($source, Assignment::ORGANISATION, true) && !$current->byOrganisation())) {
            return ['assignment' => $current, 'carried' => 0, 'changed' => false];
        }
        if (!in_array($courseId, $this->selectableCourseIds($group), true)) {
            throw VariantException::unavailable();
        }
        $organisation = in_array($source, Assignment::ORGANISATION, true);
        if (!$organisation && $group->lock_assigned && $current !== null && $current->byOrganisation()) {
            throw VariantException::locked();
        }

        $result = DB::transaction(function () use ($userId, $group, $courseId, $source, $reason, $decidedBy, $ruleId, $hintId, $current, $organisation) {
            if (!$this->access->covers($userId, $group)) {
                if ($source !== Assignment::ADMIN) {
                    // a learner's own choice and a rule only route people who already have access; they never enrol anyone
                    throw VariantException::notEnrolled();
                }
                // an admin assigned the learner: an entitlement for the chosen course (and so for the family) comes with it
                $this->entitlements->grantManual($userId, $courseId, Entitlement::SOURCE_ADMIN, null, ['variant_group' => $group->id], $decidedBy !== null ? (string) $decidedBy : null);
                $this->entitlements->project($userId, $courseId);
            }
            $this->access->enrol($userId, $group, $courseId);

            $current?->forceFill(['is_current' => null, 'superseded_at' => now()])->save();
            $assignment = Assignment::query()->create([
                'user_id' => $userId, 'group_id' => $group->id, 'course_id' => $courseId, 'source' => $source,
                'reason' => $reason, 'decided_by' => $decidedBy, 'rule_id' => $ruleId,
                'decided_at' => now(), 'is_current' => true,
            ]);
            if ($hintId !== null) {
                Hint::query()->where('id', $hintId)->where('user_id', $userId)->where('group_id', $group->id)->where('status', Hint::SHOWN)
                    ->update(['status' => Hint::ACCEPTED, 'resolved_at' => now()]);
            }

            return ['assignment' => $assignment, 'carried' => $this->carryOver->carry($userId, $group, $courseId), 'changed' => true];
        });
        event(new VariantChosen($userId, (int) $group->default_course_id, $courseId, $source, $current?->course_id));

        return $result;
    }

    /**
     * An admin puts learners in a variant (the lock of the family does not apply to them; the learner sees "assigned by
     * your organisation"). Returns the number of learners whose variant changed.
     *
     * @param int[] $userIds
     */
    public function assignMany(VariantGroup $group, int $courseId, array $userIds): int
    {
        $by = Auth::id();
        $changed = 0;
        foreach (array_values(array_unique($userIds)) as $userId) {
            $result = $this->choose((int) $userId, $group, $courseId, Assignment::ADMIN, ['key' => 'admin'], $by !== null ? (int) $by : null);
            $changed += $result['changed'] ? 1 : 0;
        }

        return $changed;
    }

    /** Learners that currently have a variant, with where they are. */
    public function summary(VariantGroup $group): array
    {
        $rows = Assignment::query()->current()->where('group_id', $group->id)->selectRaw('course_id, source, COUNT(*) AS n')->groupBy('course_id', 'source')->get();

        return $rows->map(fn ($r) => ['courseId' => (int) $r->course_id, 'source' => $r->source, 'learners' => (int) $r->n])->all();
    }
}
