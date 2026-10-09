<?php

namespace Ulams\LivingCourse\Services;

use Illuminate\Support\Facades\Cache;
use Ulams\Core\Models\User;
use Ulams\CourseBuilder\Models\Session;
use Ulams\Courses\Models\Course;
use Ulams\LivingCourse\Enums\LivingCoursePermissionsEnum;
use Ulams\LivingCourse\Events\CourseContentUpdated;
use Ulams\LivingCourse\Events\SourceCheckFailing;
use Ulams\LivingCourse\Events\SourceRevisionDetected;
use Ulams\LivingCourse\Events\UpdateProposalApplied;
use Ulams\LivingCourse\Events\UpdateProposalReady;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\LearnerNotice;
use Ulams\LivingCourse\Models\Proposal;

/** Dispatches the Living Course notification events (plan 10.2). Idempotent per proposal. */
final class Notifier
{
    /** @return User[] the session author and the course authors who may review */
    private function reviewers(Session $session): array
    {
        $users = [];
        $author = $session->author;
        if ($author instanceof User) {
            $users[$author->getKey()] = $author;
        }
        $course = $session->course_id ? Course::query()->find($session->course_id) : null;
        foreach ($course?->authors ?? [] as $a) {
            if ($a instanceof User && method_exists($a, 'can') && $a->can(LivingCoursePermissionsEnum::LIVING_COURSE_REVIEW, 'api')) {
                $users[$a->getKey()] = $a;
            }
        }

        return array_values($users);
    }

    private function courseTitle(Session $session): string
    {
        return (string) ($session->course_id ? Course::query()->whereKey($session->course_id)->value('title') : null) ?: (string) $session->title;
    }

    public function revisionDetected(Proposal $proposal): void
    {
        $session = Session::query()->find($proposal->session_id);
        if ($session?->author instanceof User) {
            SourceRevisionDetected::dispatch($session->author, ['sessionId' => $session->id, 'courseTitle' => $this->courseTitle($session), 'revision' => (int) $proposal->toRevision?->number]);
        }
    }

    /** Once per proposal: it is ready, or waits for the author to start the analysis. */
    public function proposalReady(Proposal $proposal): void
    {
        $proposal->refresh();
        if (!in_array($proposal->status, ['ready', 'awaiting_analysis', 'budget_blocked'], true) || !empty($proposal->counts['notified'])) {
            return;
        }
        $session = Session::query()->find($proposal->session_id);
        if ($session === null) {
            return;
        }
        $proposal->forceFill(['counts' => array_merge((array) $proposal->counts, ['notified' => true])])->save();
        $counts = (array) $proposal->counts;
        foreach ($this->reviewers($session) as $user) {
            UpdateProposalReady::dispatch($user, [
                'sessionId' => $session->id, 'proposalId' => $proposal->id, 'courseTitle' => $this->courseTitle($session),
                'elements' => (int) ($counts['elements'] ?? 0), 'answerChecks' => (int) ($counts['answerChecks'] ?? 0), 'status' => $proposal->status,
            ]);
        }
    }

    public function proposalApplied(Proposal $proposal): void
    {
        $session = Session::query()->find($proposal->session_id);
        if ($session?->author instanceof User) {
            UpdateProposalApplied::dispatch($session->author, ['sessionId' => $session->id, 'proposalId' => $proposal->id, 'courseTitle' => $this->courseTitle($session), 'applied' => (int) ($proposal->counts['applied'] ?? 0)]);
        }
    }

    public function checkFailing(Connection $connection): void
    {
        $session = Session::query()->find($connection->session_id);
        if ($session?->author instanceof User) {
            SourceCheckFailing::dispatch($session->author, ['sessionId' => $session->id, 'courseTitle' => $this->courseTitle($session), 'connector' => $connection->connector, 'error' => mb_substr((string) $connection->last_error, 0, 200)]);
        }
    }

    /** One event per learner with notices of this proposal; the e-mail digest allows one per course per 7 days. */
    public function learnersNotified(Proposal $proposal): int
    {
        $session = Session::query()->find($proposal->session_id);
        if ($session === null || $session->course_id === null) {
            return 0;
        }
        $title = $this->courseTitle($session);
        $sent = 0;
        $users = LearnerNotice::query()->where('proposal_id', $proposal->id)->where('status', 'open')->distinct()->pluck('user_id');
        $model = config('auth.providers.users.model');
        foreach ($model::query()->whereIn('id', $users)->get() as $user) {
            $key = "living_course:digest:{$session->course_id}:{$user->getKey()}";
            if (!Cache::add($key, 1, now()->addDays((int) config('living_course.learner_email_digest_days', 7)))) {
                continue;
            }
            CourseContentUpdated::dispatch($user, ['courseId' => (int) $session->course_id, 'courseTitle' => $title, 'proposalId' => $proposal->id]);
            $sent++;
        }

        return $sent;
    }
}
