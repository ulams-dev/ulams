<?php

namespace Ulams\CourseVariants\Services;

use Ulams\CourseVariants\Contracts\FitSignals;
use Ulams\CourseVariants\Models\Hint;
use Ulams\CourseVariants\Models\VariantGroup;

/**
 * The adaptive hint (ADR 0192, plan section 11): when transparent Learner Insights rules say the variant is too easy or
 * too hard, the learner sees a dismissible hint with the reasons and a "Switch to ..." button. Never an automatic
 * switch, at most one hint per learner and family per week, and dismissing one stops them for that family.
 */
final class HintService
{
    public const WEEK_DAYS = 7;

    public function __construct(private readonly FitSignals $fit, private readonly AssignmentService $assignments)
    {
    }

    public function pending(int $userId, VariantGroup $group): ?Hint
    {
        return Hint::query()->where('user_id', $userId)->where('group_id', $group->id)->where('status', Hint::SHOWN)->latest('id')->first();
    }

    /** The hint to show now: the one still open, or a new one when the rules fire and the limits allow it. */
    public function forLearner(int $userId, VariantGroup $group): ?Hint
    {
        $open = $this->pending($userId, $group);
        if ($open !== null) {
            // an open hint stays only while it still makes sense: the learner is still in the variant it was about
            if ((int) $open->course_id === $this->assignments->effectiveCourseId($userId, $group)) {
                return $open;
            }
            $open->forceFill(['status' => Hint::EXPIRED, 'resolved_at' => now()])->save();
        }

        return $this->evaluate($userId, $group);
    }

    public function evaluate(int $userId, VariantGroup $group): ?Hint
    {
        if (!$group->hints_enabled || !$this->assignments->mayChange($userId, $group)['allowed']) {
            return null;
        }
        $base = Hint::query()->where('user_id', $userId)->where('group_id', $group->id);
        if ((clone $base)->where('status', Hint::DISMISSED)->exists() || (clone $base)->where('created_at', '>=', now()->subDays(self::WEEK_DAYS))->exists()) {
            return null;
        }
        $courseId = $this->assignments->effectiveCourseId($userId, $group);
        $signal = $this->fit->assess($userId, $courseId);
        if ($signal === null) {
            return null;
        }
        $target = Levels::neighbour($group, $courseId, $signal['direction'] === 'harder' ? 1 : -1, $this->assignments->selectableCourseIds($group));
        if ($target === null) {
            return null;
        }

        return Hint::query()->create([
            'user_id' => $userId, 'group_id' => $group->id, 'course_id' => $courseId, 'suggested_course_id' => $target,
            'direction' => $signal['direction'], 'reasons' => $signal['reasons'], 'status' => Hint::SHOWN,
        ]);
    }

    public function dismiss(Hint $hint): Hint
    {
        if ($hint->status === Hint::SHOWN) {
            $hint->forceFill(['status' => Hint::DISMISSED, 'resolved_at' => now()])->save();
        }

        return $hint;
    }
}
