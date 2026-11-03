<?php

namespace Ulams\LearnerInsights\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Ulams\LearnerInsights\Models\Intervention;

/**
 * Closes interventions (plan 7.3). A nudge is recovered when the learner progresses again in the
 * course (finishes a topic or passes a quiz) or their status returns to on track; a remediation
 * when they do so on the element it was about. After the window an open intervention counts as not
 * recovered. A recovery rate alone is not proof of an effect: the holdout experiment measures that.
 */
class OutcomeTracker
{
    /** The learner finished a topic or passed a quiz (`$elementKey` is the element it was about). */
    public function progressed(int $userId, int $courseId, ?string $elementKey): void
    {
        $this->open($userId, $courseId)->where(function (Builder $q) use ($elementKey) {
            $q->where('kind', Intervention::NUDGE);
            if ($elementKey !== null) {
                $q->orWhere('element_key', $elementKey);
            }
        })->get()->each(fn (Intervention $i) => $this->resolve($i, Intervention::RECOVERED));
    }

    /** Status back to on track: the course, or one element. */
    public function recovered(int $userId, int $courseId, ?string $elementKey): void
    {
        $this->open($userId, $courseId)->where(fn (Builder $q) => $elementKey === null
            ? $q->where('kind', Intervention::NUDGE)
            : $q->where('element_key', $elementKey))->get()
            ->each(fn (Intervention $i) => $this->resolve($i, Intervention::RECOVERED));
    }

    /** @return int interventions closed as not recovered */
    public function closeExpired(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $n = 0;
        Intervention::query()->where('outcome', Intervention::PENDING)->get()->each(function (Intervention $i) use ($now, &$n) {
            if ($i->created_at->addDays($i->window_days)->lessThanOrEqualTo($now)) {
                $this->resolve($i, Intervention::NOT_RECOVERED);
                $n++;
            }
        });

        return $n;
    }

    /**
     * recovered / (recovered + not recovered); null while nothing is closed.
     *
     * @return array{recovered:int,closed:int,rate:?float}
     */
    public function rate(?int $courseId = null, ?string $kind = null, ?string $elementKey = null): array
    {
        $q = Intervention::query()->whereIn('outcome', [Intervention::RECOVERED, Intervention::NOT_RECOVERED]);
        $courseId !== null && $q->where('course_id', $courseId);
        $kind !== null && $q->where('kind', $kind);
        $elementKey !== null && $q->where('element_key', $elementKey);
        $closed = (clone $q)->count();
        $recovered = (clone $q)->where('outcome', Intervention::RECOVERED)->count();

        return ['recovered' => $recovered, 'closed' => $closed, 'rate' => $closed > 0 ? round($recovered / $closed, 4) : null];
    }

    /**
     * Recovery of learners who got personal help against the holdout that did not (same window, same measure).
     * Not proof of an effect on its own: the groups are only comparable when the holdout is a stable random share.
     *
     * @return array{helped:array{recovered:int,closed:int,rate:?float},holdout:array{recovered:int,closed:int,rate:?float}}
     */
    public function helpedVsHoldout(?int $courseId = null): array
    {
        $count = function (bool $holdout) use ($courseId) {
            $q = Intervention::query()->where('kind', Intervention::REMEDIATION)->whereIn('outcome', [Intervention::RECOVERED, Intervention::NOT_RECOVERED]);
            $courseId !== null && $q->where('course_id', $courseId);
            $holdout ? $q->where('detail', 'holdout') : $q->whereNull('detail');
            $closed = (clone $q)->count();
            $recovered = (clone $q)->where('outcome', Intervention::RECOVERED)->count();

            return ['recovered' => $recovered, 'closed' => $closed, 'rate' => $closed > 0 ? round($recovered / $closed, 4) : null];
        };

        return ['helped' => $count(false), 'holdout' => $count(true)];
    }

    private function open(int $userId, int $courseId): Builder
    {
        return Intervention::query()->where('user_id', $userId)->where('course_id', $courseId)->where('outcome', Intervention::PENDING);
    }

    private function resolve(Intervention $i, string $outcome): void
    {
        $i->forceFill(['outcome' => $outcome, 'resolved_at' => CarbonImmutable::now()])->save();
    }
}
