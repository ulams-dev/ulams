<?php

namespace Ulams\LearnerInsights\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ulams\LearnerInsights\Services\SignalRecorder;
use Ulams\LearnerInsights\Signals\SignalData;
use Ulams\LearnerInsights\Signals\SignalType;

/**
 * Turns what the LMS already stores into signals (source `backfill`, `source_ref` = `<table>:<id>`),
 * so a re-run adds nothing. Progress and SCORM keep only the latest state, so the history is
 * coarse. Respects opt-outs, the module switch and the retention window. Writes only to
 * `learner_signals`.
 */
class BackfillCommand extends Command
{
    protected $signature = 'learner-insights:backfill {--course= : Only this course id} {--since= : Only rows changed since this date} {--chunk=500} {--dry-run}';

    protected $description = 'Create learner signals from existing progress, quiz and SCORM data (idempotent)';

    private int $written = 0;

    private int $seen = 0;

    public function handle(SignalRecorder $recorder): int
    {
        if (!config('learner_insights.enabled')) {
            $this->warn('Learner insights is disabled for this tenant (learner_insights.enabled); nothing is recorded.');

            return self::SUCCESS;
        }
        $chunk = max(1, (int) $this->option('chunk'));
        $dry = (bool) $this->option('dry-run');
        $floor = CarbonImmutable::now()->subDays((int) config('learner_insights.retention.signals_days'));
        $since = $this->option('since') ? CarbonImmutable::parse($this->option('since')) : null;
        $floor = $since && $since->greaterThan($floor) ? $since : $floor;
        $course = $this->option('course') ? (int) $this->option('course') : null;

        $emit = function (SignalData $signal) use ($recorder, $dry, $floor) {
            if ($signal->occurredAt->lessThan($floor)) {
                return;
            }
            $this->seen++;
            if (!$dry && $recorder->record($signal)) {
                $this->written++;
            }
        };

        // course_progress: one completed or in-progress state per learner and topic
        $q = DB::table('course_progress')->orderBy('id');
        if ($course) {
            $q->whereIn('topic_id', DB::table('topics')->join('lessons', 'lessons.id', '=', 'topics.lesson_id')->where('lessons.course_id', $course)->select('topics.id'));
        }
        $q->chunkById($chunk, function ($rows) use ($emit) {
            foreach ($rows as $row) {
                $at = CarbonImmutable::parse($row->updated_at ?? $row->created_at);
                $emit(new SignalData((int) $row->user_id, SignalType::TOPIC_PROGRESSED, 'backfill', 'course_progress:' . $row->id, $at, null, (int) $row->topic_id, null, $row->seconds === null ? null : (float) $row->seconds, ['status' => (int) $row->status]));
                if ($row->finished_at) {
                    $emit(new SignalData((int) $row->user_id, SignalType::TOPIC_COMPLETED, 'backfill', 'course_progress:' . $row->id, CarbonImmutable::parse($row->finished_at), null, (int) $row->topic_id));
                }
            }
        });

        // course_user: enrolment start and completion
        $q = DB::table('course_user')->orderBy('id');
        if ($course) {
            $q->where('course_id', $course);
        }
        $q->chunkById($chunk, function ($rows) use ($emit) {
            foreach ($rows as $row) {
                $at = CarbonImmutable::parse($row->updated_at ?? $row->created_at);
                $emit(new SignalData((int) $row->user_id, SignalType::COURSE_STARTED, 'backfill', 'course_user:' . $row->id, CarbonImmutable::parse($row->created_at ?? $at), (int) $row->course_id));
                if (!empty($row->finished)) {
                    $emit(new SignalData((int) $row->user_id, SignalType::COURSE_COMPLETED, 'backfill', 'course_user:' . $row->id, $at, (int) $row->course_id));
                }
            }
        });

        // H5P results: the latest statement per learner, topic and verb
        if (DB::getSchemaBuilder()->hasTable('h5p_user_progress')) {
            $q = DB::table('h5p_user_progress')->orderBy('id');
            if ($course) {
                $q->whereIn('topic_id', DB::table('topics')->join('lessons', 'lessons.id', '=', 'topics.lesson_id')->where('lessons.course_id', $course)->select('topics.id'));
            }
            $q->chunkById($chunk, function ($rows) use ($emit) {
                foreach ($rows as $row) {
                    $scaled = data_get((array) json_decode((string) $row->data, true), 'result.score.scaled');
                    $emit(new SignalData((int) $row->user_id, SignalType::H5P_RESULT, 'backfill', 'h5p_user_progress:' . $row->id, CarbonImmutable::parse($row->updated_at ?? $row->created_at), null, (int) $row->topic_id, null, is_numeric($scaled) ? round(max(0.0, min(1.0, (float) $scaled)), 4) : null, ['verb' => (string) $row->event]));
                }
            });
        }

        // GIFT attempts and graded answers
        if (DB::getSchemaBuilder()->hasTable('topic_gift_quiz_attempts')) {
            $morph = (new \Ulams\TopicTypeGift\Models\GiftQuiz())->getMorphClass();
            $topicOf = fn ($quizId) => DB::table('topics')->where('topicable_type', $morph)->where('topicable_id', $quizId)->value('id');
            $q = DB::table('topic_gift_quiz_attempts')->orderBy('id');
            $q->chunkById($chunk, function ($rows) use ($emit, $topicOf, $course) {
                foreach ($rows as $row) {
                    $topicId = $topicOf($row->topic_gift_quiz_id);
                    if ($course && (!$topicId || (int) DB::table('topics')->join('lessons', 'lessons.id', '=', 'topics.lesson_id')->where('topics.id', $topicId)->value('lessons.course_id') !== $course)) {
                        continue;
                    }
                    $emit(new SignalData((int) $row->user_id, SignalType::QUIZ_ATTEMPT_STARTED, 'backfill', 'gift_attempt:' . $row->id, CarbonImmutable::parse($row->started_at ?? $row->created_at), null, $topicId));
                    foreach (DB::table('topic_gift_attempt_answers')->where('topic_gift_quiz_attempt_id', $row->id)->get() as $answer) {
                        $max = (float) DB::table('topic_gift_questions')->where('id', $answer->topic_gift_question_id)->value('score');
                        if ($answer->score === null || $max <= 0) {
                            continue;
                        }
                        $ratio = max(0.0, min(1.0, (float) $answer->score / $max));
                        $emit(new SignalData((int) $row->user_id, SignalType::QUIZ_ANSWER, 'backfill', 'gift_answer:' . $answer->id, CarbonImmutable::parse($answer->updated_at ?? $answer->created_at), null, $topicId, (int) $answer->topic_gift_question_id, round($ratio, 4), ['question_id' => (int) $answer->topic_gift_question_id, 'correct' => $ratio >= 1.0]));
                    }
                }
            });
        }

        $this->info(($dry ? 'Would record ' : 'Recorded ') . ($dry ? $this->seen : $this->written) . " signals ({$this->seen} candidates).");

        return self::SUCCESS;
    }
}
