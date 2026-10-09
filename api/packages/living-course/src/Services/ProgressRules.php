<?php

namespace Ulams\LivingCourse\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\Topic;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\LearnerNotice;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Models\QuizAttempt;

/**
 * Progress preservation (ADR 0033). An accepted update never changes a score, an attempt, a
 * completion or a progress row. It only tells learners: "updated since you completed it" for major
 * lesson changes, "one question was corrected" with one extra attempt for changed answers, "retired
 * lesson" for removed topics they completed, and "new since you finished" for added lessons.
 */
final class ProgressRules
{
    public const REATTEMPT_MESSAGE = 'One question was corrected. Your previous score stays on record. Retake the quiz to update your result.';

    public function __construct(private readonly AuditLog $audit)
    {
    }

    /** What an update does for learners: notice specs with the learners they reach. @return array<int,array{kind:string,topicId:int,questionId:int,users:Builder,label:?string}> */
    public function specs(Proposal $proposal, array $statuses = ['accepted']): array
    {
        $session = Session::withTrashed()->findOrFail($proposal->session_id);
        $base = Version::query()->find($proposal->base_version_id);
        if ($session->course_id === null || $base === null) {
            return [];
        }
        $doc = $base->document;
        $specs = [];
        $map = fn (string $element, array $types) => EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $element)->whereIn('entity_type', $types)->value('entity_id');
        $items = ProposalItem::query()->where('proposal_id', $proposal->id)->whereIn('status', $statuses)->where('kind', 'update')->get();

        $updatedTopics = [];
        foreach ($items as $item) {
            $found = Blueprint::find($doc, $item->element_id);
            if ($found === null || $found['lessonId'] === null) {
                continue;
            }
            $lesson = Blueprint::find($doc, $found['lessonId'])['node'];
            if ($item->element_type === 'block' && $item->change_class === 'major') {
                $topic = $map($lesson['id'], ['topic']);
                if ($topic !== null && !isset($updatedTopics[$topic])) {
                    $updatedTopics[$topic] = true;
                    $specs[] = ['kind' => 'topic_updated', 'topicId' => (int) $topic, 'questionId' => 0, 'users' => $this->started((int) $topic), 'label' => $lesson['title']];
                }
            }
            if ($item->element_type === 'question' && $item->change_class === 'answer_changed') {
                $quizTopic = $map($lesson['quiz']['id'] ?? $item->element_id, ['quiz_topic']);
                $question = $map($item->element_id, ['gift_question']);
                if ($found['lessonId'] !== null && $quizTopic !== null && $question !== null) {
                    $specs[] = ['kind' => 'question_reattempt', 'topicId' => (int) $quizTopic, 'questionId' => (int) $question, 'users' => $this->answered((int) $question), 'label' => $lesson['title']];
                }
            }
        }
        // the final test has no lesson: its questions are mapped through the quiz of the document
        foreach ($items as $item) {
            if ($item->element_type !== 'question' || $item->change_class !== 'answer_changed' || ($doc['finalTest']['id'] ?? null) === null) {
                continue;
            }
            if (!collect($doc['finalTest']['questions'] ?? [])->contains(fn ($q) => $q['id'] === $item->element_id)) {
                continue;
            }
            $quizTopic = $map($doc['finalTest']['id'], ['quiz_topic']);
            $question = $map($item->element_id, ['gift_question']);
            if ($quizTopic !== null && $question !== null) {
                $specs[] = ['kind' => 'question_reattempt', 'topicId' => (int) $quizTopic, 'questionId' => (int) $question, 'users' => $this->answered((int) $question), 'label' => 'Final test'];
            }
        }

        if ($proposal->applied_at !== null || $proposal->status === 'applying' || $proposal->status === 'applied') {
            $since = $proposal->decided_at ?? $proposal->created_at;
            foreach (EntityMapEntry::query()->where('session_id', $session->id)->whereIn('entity_type', ['topic', 'quiz_topic'])->whereNotNull('retired_at')->where('retired_at', '>=', $since)->get() as $entry) {
                $specs[] = ['kind' => 'topic_retired', 'topicId' => (int) $entry->entity_id, 'questionId' => 0, 'users' => $this->completed((int) $entry->entity_id), 'label' => Topic::query()->whereKey($entry->entity_id)->value('title')];
            }
            foreach (EntityMapEntry::query()->where('session_id', $session->id)->whereIn('entity_type', ['topic', 'quiz_topic'])->where('added_by_proposal_id', $proposal->id)->get() as $entry) {
                $specs[] = ['kind' => 'course_extended', 'topicId' => (int) $entry->entity_id, 'questionId' => 0, 'users' => $this->finishedCourse((int) $session->course_id), 'label' => Topic::query()->whereKey($entry->entity_id)->value('title')];
            }
        }

        return $specs;
    }

    /** Counts per kind for the review screen: how many learners each rule reaches. @return array<string,int> */
    public function impact(Proposal $proposal, array $statuses = ['accepted']): array
    {
        $counts = array_fill_keys(LearnerNotice::KINDS, 0);
        $seen = [];
        foreach ($this->specs($proposal, $statuses) as $spec) {
            foreach ($spec['users']->pluck('user_id')->all() as $userId) {
                $seen[$spec['kind']][$userId] = true;
            }
        }
        foreach ($seen as $kind => $users) {
            $counts[$kind] = count($users);
        }

        return $counts;
    }

    /** The text learners see for updated lessons: the editable note, else the reasons of the major changes. */
    public function note(Proposal $proposal): string
    {
        if (trim((string) $proposal->learner_note) !== '') {
            return mb_substr(trim((string) $proposal->learner_note), 0, 500);
        }
        $reasons = ProposalItem::query()->where('proposal_id', $proposal->id)->whereIn('status', ['accepted', 'pending'])->where('kind', 'update')->whereIn('change_class', ['major', 'answer_changed'])->orderBy('id')->pluck('reason')->unique()->take(4)->all();

        return mb_substr(implode(' ', $reasons), 0, 500);
    }

    /** Creates the notices after an apply (idempotent) and returns the number created per kind. @return array<string,int> */
    public function run(Proposal $proposal): array
    {
        $session = Session::withTrashed()->findOrFail($proposal->session_id);
        $connection = Connection::query()->where('source_id', $proposal->source_id)->first();
        $inform = (bool) ($connection?->setting('notify_learners_of_updates', true) ?? true);
        $note = $this->note($proposal);
        $created = array_fill_keys(LearnerNotice::KINDS, 0);
        foreach ($this->specs($proposal) as $spec) {
            if (!$inform && $spec['kind'] !== 'question_reattempt') {
                continue;
            }
            $message = match ($spec['kind']) {
                'question_reattempt' => self::REATTEMPT_MESSAGE,
                'topic_retired' => 'This lesson is no longer part of the course. Your progress stays on record.',
                'course_extended' => 'New since you finished: ' . ($spec['label'] ?? 'a new lesson') . '.',
                default => $note !== '' ? $note : 'This lesson was updated after you started it.',
            };
            $spec['users']->orderBy('user_id')->select('user_id')->distinct()->chunk(500, function ($users) use ($spec, $proposal, $session, $message, &$created) {
                $rows = [];
                foreach ($users as $u) {
                    $rows[] = [
                        'user_id' => $u->user_id, 'course_id' => $session->course_id, 'topic_id' => $spec['topicId'], 'gift_question_id' => $spec['questionId'],
                        'kind' => $spec['kind'], 'proposal_id' => $proposal->id, 'message' => $message, 'status' => 'open', 'created_at' => now(),
                    ];
                }
                $created[$spec['kind']] += (int) DB::table('living_course_learner_notices')->insertOrIgnore($rows);
            });
        }
        $this->audit->record('progress.rules_applied', [
            'session_id' => $session->id, 'subject_type' => 'proposal', 'subject_id' => $proposal->id, 'source_id' => $proposal->source_id, 'revision_id' => $proposal->to_revision_id,
            'actor_type' => 'system', 'data' => ['number' => $proposal->number, 'notices' => $created, 'unchanged' => 'completion, scores, attempts and answers'],
        ]);
        if (array_sum($created) > 0) {
            $this->audit->record('notice.created', [
                'session_id' => $session->id, 'subject_type' => 'proposal', 'subject_id' => $proposal->id, 'source_id' => $proposal->source_id, 'actor_type' => 'system', 'data' => $created,
            ]);
        }

        return $created;
    }

    /** A learner who retook the quiz: the re-attempt notices of that quiz are done. */
    public function attemptFinished(QuizAttempt $attempt): void
    {
        $topicIds = Topic::query()->where('topicable_type', (new GiftQuiz())->getMorphClass())->where('topicable_id', $attempt->topic_gift_quiz_id)->pluck('id');
        LearnerNotice::query()->where('user_id', $attempt->user_id)->where('kind', 'question_reattempt')->where('status', 'open')->whereIn('topic_id', $topicIds)
            ->update(['status' => 'done', 'resolved_at' => now()]);
    }

    private function started(int $topicId): Builder
    {
        return DB::table('course_progress')->where('topic_id', $topicId)->where('status', '!=', ProgressStatus::INCOMPLETE);
    }

    private function completed(int $topicId): Builder
    {
        return DB::table('course_progress')->where('topic_id', $topicId)->where('status', ProgressStatus::COMPLETE);
    }

    private function answered(int $questionId): Builder
    {
        return DB::table('topic_gift_attempt_answers')->join('topic_gift_quiz_attempts', 'topic_gift_quiz_attempts.id', '=', 'topic_gift_attempt_answers.topic_gift_quiz_attempt_id')
            ->where('topic_gift_attempt_answers.topic_gift_question_id', $questionId)->select('topic_gift_quiz_attempts.user_id as user_id');
    }

    private function finishedCourse(int $courseId): Builder
    {
        return DB::table('course_user')->where('course_id', $courseId)->where('finished', true)->select('user_id');
    }
}
