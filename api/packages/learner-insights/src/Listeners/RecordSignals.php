<?php

namespace Ulams\LearnerInsights\Listeners;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Ulams\Auth\Events\Login;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Events\CourseStarted;
use Ulams\Courses\Events\TopicFinished;
use Ulams\Courses\Events\TopicProgressUpdated;
use Ulams\Courses\Models\CourseProgress;
use Ulams\Courses\Models\Topic;
use Ulams\LearnerInsights\Services\Eligibility;
use Ulams\LearnerInsights\Services\SignalRecorder;
use Ulams\LearnerInsights\Signals\SignalData;
use Ulams\LearnerInsights\Signals\SignalType;
use Ulams\Scorm\Events\ScormScoCompleted;
use Ulams\TopicTypeGift\Events\QuizAttemptFinishedEvent;
use Ulams\TopicTypeGift\Events\QuizAttemptStartedEvent;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Models\QuizAttempt;
use Ulams\TopicTypes\Models\TopicContent\ScormSco;

/**
 * Turns events of the courses, GIFT, SCORM and auth packages into learner signals (ADR 0057).
 * Everything goes through SignalRecorder::queue(), after commit and off the request; with the
 * module off nothing is queued.
 */
class RecordSignals
{
    public function __construct(private readonly SignalRecorder $recorder, private readonly Eligibility $eligibility)
    {
    }

    public function courseStarted(CourseStarted $e): void
    {
        $this->course(SignalType::COURSE_STARTED, 'course_started', $e->getUser()->getKey(), $e->getCourse()->getKey());
    }

    public function courseFinished(CourseFinished $e): void
    {
        $this->course(SignalType::COURSE_COMPLETED, 'course_completed', $e->getUser()->getKey(), $e->getCourse()->getKey());
    }

    public function topicFinished(TopicFinished $e): void
    {
        $user = (int) $e->getUser()->getKey();
        $topic = $e->getTopic();
        $this->push(new SignalData($user, SignalType::TOPIC_COMPLETED, 'progress', "topic_completed:$user:{$topic->getKey()}", CarbonImmutable::now(), null, (int) $topic->getKey()));
    }

    public function topicProgressed(TopicProgressUpdated $e): void
    {
        if (!$this->eligibility->moduleEnabled()) {
            return;
        }
        $now = CarbonImmutable::now();
        $this->push(new SignalData($e->userId, SignalType::TOPIC_PROGRESSED, 'progress', 'progress:' . Str::uuid(), $now, null, $e->topicId, null, $e->secondsDelta === null ? null : (float) $e->secondsDelta, ['status' => $e->status]));

        // one "opened" per learner, topic and day; `revisit` when the topic was completed before
        if ($e->status === ProgressStatus::IN_PROGRESS) {
            $revisit = CourseProgress::query()->where('user_id', $e->userId)->where('topic_id', $e->topicId)->whereNotNull('finished_at')->exists();
            $this->push(new SignalData($e->userId, SignalType::TOPIC_OPENED, 'progress', "opened:{$e->userId}:{$e->topicId}:" . $now->format('Y-m-d'), $now, null, $e->topicId, null, null, ['revisit' => $revisit]));
        }
    }

    public function quizStarted(QuizAttemptStartedEvent $e): void
    {
        $attempt = $e->getAttempt();
        $user = (int) $attempt->user_id;
        $this->push(new SignalData($user, SignalType::QUIZ_ATTEMPT_STARTED, 'gift', 'gift_attempt_started:' . $attempt->getKey(), CarbonImmutable::now(), null, $this->topicOfQuiz($attempt), null, null, ['attempt_no' => $this->attemptNo($attempt)]));
    }

    public function quizFinished(QuizAttemptFinishedEvent $e): void
    {
        if (!$this->eligibility->moduleEnabled()) {
            return;
        }
        $attempt = $e->getAttempt();
        $user = (int) $attempt->user_id;
        $topicId = $this->topicOfQuiz($attempt);
        $now = CarbonImmutable::now();
        $percent = $attempt->result_percent;

        $this->push(new SignalData($user, SignalType::QUIZ_ATTEMPT_FINISHED, 'gift', 'gift_attempt_finished:' . $attempt->getKey(), $now, null, $topicId, null, $percent === null ? null : round($percent / 100, 4), ['attempt_no' => $this->attemptNo($attempt), 'passed' => $attempt->is_passed]));

        // one signal per graded answer; ungraded (open) answers have no ratio yet
        foreach ($attempt->answers()->with('question')->get() as $answer) {
            $max = (float) ($answer->question?->score ?? 0);
            if ($answer->graded_at === null && $answer->score === null || $max <= 0) {
                continue;
            }
            $ratio = max(0.0, min(1.0, (float) $answer->score / $max));
            $this->push(new SignalData($user, SignalType::QUIZ_ANSWER, 'gift', 'gift_answer:' . $answer->getKey(), $now, null, $topicId, (int) $answer->topic_gift_question_id, round($ratio, 4), ['question_id' => (int) $answer->topic_gift_question_id, 'correct' => $ratio >= 1.0]));
        }
    }

    public function scormCompleted(ScormScoCompleted $e): void
    {
        if (!$this->eligibility->moduleEnabled()) {
            return;
        }
        $topics = Topic::query()->where('topicable_type', ScormSco::class)
            ->whereIn('topicable_id', ScormSco::query()->where('value', $e->scoId)->select('id'))->pluck('id');
        foreach ($topics as $topicId) {
            $this->push(new SignalData($e->userId, SignalType::SCORM_STATUS, 'scorm', "scorm_sco:{$e->userId}:{$e->scoId}:$topicId", CarbonImmutable::now(), null, (int) $topicId, null, null, ['lesson_status' => 'completed']));
        }
    }

    public function login(Login $e): void
    {
        $user = (int) $e->getUser()->getAuthIdentifier();
        $this->push(new SignalData($user, SignalType::SESSION_LOGIN, 'auth', 'login:' . Str::uuid(), CarbonImmutable::now()));
    }

    private function course(string $type, string $ref, int $userId, int $courseId): void
    {
        $this->push(new SignalData($userId, $type, 'progress', "$ref:$userId:$courseId", CarbonImmutable::now(), $courseId));
    }

    private function push(SignalData $signal): void
    {
        $this->recorder->queue($signal);
    }

    private function topicOfQuiz(QuizAttempt $attempt): ?int
    {
        return Topic::query()->where('topicable_type', (new GiftQuiz())->getMorphClass())->where('topicable_id', $attempt->topic_gift_quiz_id)->value('id');
    }

    private function attemptNo(QuizAttempt $attempt): int
    {
        return QuizAttempt::query()->where('user_id', $attempt->user_id)->where('topic_gift_quiz_id', $attempt->topic_gift_quiz_id)->where('id', '<=', $attempt->getKey())->count();
    }
}
