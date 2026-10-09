<?php

namespace Ulams\Lti\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Packback\Lti1p3\LtiAssignmentsGradesService;
use Packback\Lti1p3\LtiGrade;
use Packback\Lti1p3\LtiLineitem;
use Throwable;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\CourseProgress;
use Ulams\Courses\Models\Topic;
use Ulams\Lti\Models\LtiGradeTarget;
use Ulams\Lti\Tool\ToolDatabase;
use Ulams\Lti\Tool\ToolLaunchService;

/**
 * Tool side grade passback: sends the learner's course progress (percentage of finished topics)
 * to the platform's line item through AGS. Retried with backoff; the last error is kept on the
 * grade target for the admin.
 */
class SendGradeToPlatform implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public function __construct(public readonly int $gradeTargetId)
    {
    }

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(ToolDatabase $db, ToolLaunchService $launches): void
    {
        /** @var LtiGradeTarget|null $target */
        $target = LtiGradeTarget::query()->with('platform')->find($this->gradeTargetId);
        if ($target === null || $target->platform === null || !$target->platform->enabled) {
            return;
        }

        [$finished, $total] = $this->progress($target->user_id, $target->course_id);
        $percent = $total > 0 ? round($finished / $total * 100, 2) : 0.0;
        $completed = $total > 0 && $finished >= $total;

        $grade = new LtiGrade([
            'scoreGiven' => $percent,
            'scoreMaximum' => 100,
            'activityProgress' => $completed ? 'Completed' : 'InProgress',
            'gradingProgress' => $completed ? 'FullyGraded' : 'Pending',
            'timestamp' => Carbon::now()->toIso8601String(),
            'userId' => $target->sub,
        ]);

        $service = new LtiAssignmentsGradesService(
            $launches->connector(),
            $db->registration($target->platform),
            array_filter([
                'scope' => $target->scopes,
                'lineitem' => $target->lineitem,
                'lineitems' => $target->lineitems,
            ])
        );

        try {
            $lineItem = $target->lineitem ? LtiLineitem::new()->setId($target->lineitem) : null;
            if ($lineItem === null) {
                $lineItem = $service->findOrCreateLineitem(LtiLineitem::new()
                    ->setLabel('Course progress')
                    ->setScoreMaximum(100)
                    ->setResourceId('course-' . $target->course_id));
            }
            $service->putGrade($grade, $lineItem);
            $target->update(['last_sent_at' => Carbon::now(), 'last_error' => null]);
        } catch (Throwable $e) {
            $target->update(['last_error' => mb_substr($e->getMessage(), 0, 1000)]);
            throw $e;
        }
    }

    /**
     * @return array{0: int, 1: int} finished and total active topics of the course
     */
    private function progress(int $userId, int $courseId): array
    {
        $topicIds = Topic::query()
            ->where('active', true)
            ->whereHas('lesson', fn ($q) => $q->where('course_id', $courseId))
            ->pluck('id');
        $finished = CourseProgress::query()
            ->where('user_id', $userId)
            ->whereIn('topic_id', $topicIds)
            ->where('status', ProgressStatus::COMPLETE)
            ->count();

        return [$finished, $topicIds->count()];
    }
}
