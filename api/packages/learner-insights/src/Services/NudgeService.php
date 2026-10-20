<?php

namespace Ulams\LearnerInsights\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Ulams\Core\Models\User;
use Ulams\Courses\Models\Course;
use Ulams\LearnerInsights\Events\LearnerNudged;
use Ulams\LearnerInsights\Models\Intervention;
use Ulams\LearnerInsights\Scoring\Reason;
use Ulams\LearnerInsights\Signals\SignalData;
use Ulams\LearnerInsights\Signals\SignalType;

/**
 * Decides whether an at-risk learner gets a nudge and sends it through the notifications package
 * (in-app and e-mail). Rate limited per learner and course and per learner; every suppression is
 * logged in `learner_interventions` with its reason (plan 7.1).
 */
class NudgeService
{
    public function __construct(private readonly Privacy $privacy, private readonly Eligibility $eligibility, private readonly SignalRecorder $signals)
    {
    }

    /** @param array<int,array> $reasons reason arrays as stored with the status */
    public function consider(int $userId, int $courseId, array $reasons): ?Intervention
    {
        if (!$this->eligibility->allowed($userId) || !config('learner_insights.nudges.enabled')) {
            return null; // the module or nudges are off, or the learner opted out of everything: nothing to log
        }
        $now = CarbonImmutable::now();

        $enrolment = DB::table('course_user')->where('user_id', $userId)->where('course_id', $courseId)->first();
        if ($enrolment === null || ($enrolment->finished ?? false)) {
            return $this->suppress($userId, $courseId, 'not_active');
        }
        if (!$this->privacy->settings($userId)['nudges']) {
            return $this->suppress($userId, $courseId, 'learner_preference');
        }
        $sent = Intervention::query()->where('user_id', $userId)->where('kind', Intervention::NUDGE)->where('outcome', '!=', Intervention::SUPPRESSED);
        if ((clone $sent)->where('course_id', $courseId)->where('created_at', '>=', $now->subDays((int) config('learner_insights.nudges.per_course_days')))->exists()) {
            return $this->suppress($userId, $courseId, 'rate_limit_course');
        }
        if ((clone $sent)->where('created_at', '>=', $now->subDays(7))->count() >= (int) config('learner_insights.nudges.per_learner_week')) {
            return $this->suppress($userId, $courseId, 'rate_limit_learner');
        }

        $user = User::query()->find($userId);
        $course = Course::query()->find($courseId);
        if ($user === null || $course === null) {
            return null;
        }

        $nudge = Intervention::query()->create([
            'user_id' => $userId, 'course_id' => $courseId, 'kind' => Intervention::NUDGE, 'outcome' => Intervention::PENDING,
            'window_days' => (int) config('learner_insights.interventions.window_days'), 'created_at' => $now,
        ]);

        $first = $reasons[0] ?? null;
        $locale = method_exists($user, 'preferredLocale') ? $user->preferredLocale() : null;
        $reasonText = $first ? Reason::fromArray($first)->message($locale) : '';
        $this->signals->record(new SignalData($userId, SignalType::NUDGE_SENT, 'nudge', 'nudge:' . $nudge->id, $now, $courseId, null, null, null, ['nudge_id' => $nudge->id, 'channel' => 'email']));

        LearnerNudged::dispatch($user, [
            'courseId' => $courseId,
            'courseTitle' => (string) $course->title,
            'reasonText' => $reasonText,
            'continueUrl' => $this->continueUrl($userId, $courseId, $nudge->id),
            'nudgeId' => $nudge->id,
        ]);

        return $nudge;
    }

    /**
     * Learners at risk who were never considered in the current window (the event may have been
     * missed, or the tenant switched nudges on later). Not logged again if already suppressed.
     */
    public function sweep(): int
    {
        if (!config('learner_insights.nudges.enabled') || !config('learner_insights.enabled')) {
            return 0;
        }
        $window = CarbonImmutable::now()->subDays((int) config('learner_insights.nudges.per_course_days'));
        $n = 0;
        $rows = DB::table('learner_statuses as s')->where('s.element_key', '')->where('s.status', 'at_risk')
            ->whereNotExists(fn ($q) => $q->from('learner_interventions as i')->whereColumn('i.user_id', 's.user_id')->whereColumn('i.course_id', 's.course_id')->where('i.kind', Intervention::NUDGE)->where('i.created_at', '>=', $window))
            ->get(['s.user_id', 's.course_id', 's.reasons']);
        foreach ($rows as $row) {
            $this->consider((int) $row->user_id, (int) $row->course_id, json_decode($row->reasons, true) ?: []);
            $n++;
        }

        return $n;
    }

    /** Link that opens the next unfinished topic; `n` is signed so an open can be attributed without a session. */
    public function continueUrl(int $userId, int $courseId, string $nudgeId): string
    {
        $done = DB::table('course_progress')->where('user_id', $userId)->where('status', 1)->select('topic_id');
        $topicId = DB::table('topics')->join('lessons', 'lessons.id', '=', 'topics.lesson_id')->where('lessons.course_id', $courseId)->where('topics.active', true)
            ->whereNotIn('topics.id', $done)->orderBy('lessons.order')->orderBy('topics.order')->orderBy('topics.id')->value('topics.id');
        $front = rtrim((string) (config('learner_insights.front_url') ?: config('course_builder.front_url') ?: config('app.url')), '/');

        return $front . '/learn/' . $courseId . ($topicId ? '/' . $topicId : '') . '?n=' . $this->sign($nudgeId);
    }

    public function sign(string $nudgeId): string
    {
        return $nudgeId . '.' . substr(hash_hmac('sha256', 'nudge:' . $nudgeId, (string) config('app.key')), 0, 16);
    }

    /** @return string|null the nudge id when the token is genuine */
    public function verify(string $token): ?string
    {
        [$id] = array_pad(explode('.', $token, 2), 2, '');

        return $id !== '' && hash_equals($this->sign($id), $token) ? $id : null;
    }

    private function suppress(int $userId, int $courseId, string $why): Intervention
    {
        return Intervention::query()->create([
            'user_id' => $userId, 'course_id' => $courseId, 'kind' => Intervention::NUDGE, 'outcome' => Intervention::SUPPRESSED,
            'detail' => $why, 'window_days' => 0, 'created_at' => CarbonImmutable::now(), 'resolved_at' => CarbonImmutable::now(),
        ]);
    }
}
