<?php

namespace Ulams\Tutor\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Throwable;
use Ulams\Courses\Models\Course;
use Ulams\Tutor\Models\Conversation;
use Ulams\Tutor\Services\TutorService;
use Ulams\Tutor\Streaming\DeltaBuffer;
use Ulams\Tutor\Streaming\RunLog;

/**
 * Writes one tutor answer and appends its AG-UI events (ADR 0105). It reuses `TutorService::answer()`, so
 * retrieval, the attempt guard, the leak check, the stored reply and the signal are the same code as the
 * non-streaming request. Text events carry a draft; `tutor.final` carries the validated answer with its
 * citations and replaces it. One try: a second model call would spend twice, and the learner can ask again.
 */
class AnswerJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(
        public readonly string $runId,
        public readonly string $conversationId,
        public readonly int $courseId,
        public readonly int $userId,
        public readonly string $question,
        public readonly ?int $topicId,
        public readonly string $questionId,
    ) {
        $this->timeout = (int) config('tutor.stream.timeout_seconds');
    }

    public static function dispatchFor(self $job): void
    {
        if ($c = config('tutor.stream.queue_connection')) {
            $job->onConnection($c);
        }
        if ($q = config('tutor.stream.queue')) {
            $job->onQueue($q);
        }
        dispatch($job);
    }

    public function handle(TutorService $tutor, RunLog $log): void
    {
        $conversation = Conversation::query()->find($this->conversationId);
        $course = Course::query()->find($this->courseId);
        if ($conversation === null || $course === null) {
            $log->error($this->runId, $this->conversationId, 'The conversation is gone.', 'gone');

            return;
        }

        $messageId = 'msg_' . strtolower((string) Str::ulid());
        $started = false;
        $buffer = new DeltaBuffer(function (string $text) use ($log, &$started, $messageId) {
            if (!$started) {
                $started = true;
                $log->append($this->runId, $this->conversationId, 'TEXT_MESSAGE_START', ['messageId' => $messageId, 'role' => 'assistant']);
            }
            $log->append($this->runId, $this->conversationId, 'TEXT_MESSAGE_CONTENT', ['messageId' => $messageId, 'delta' => $text]);
        }, (int) config('tutor.stream.coalesce_ms'));

        try {
            $result = $tutor->answer($conversation, $course, $this->userId, $this->question, $this->questionId, $this->topicId, fn (string $delta) => $buffer->push($delta));
            $buffer->flush();
            if ($started) {
                $log->append($this->runId, $this->conversationId, 'TEXT_MESSAGE_END', ['messageId' => $messageId]);
            }
            $final = $result + ['questionId' => $this->questionId, 'label' => TutorService::LABEL];
            $log->append($this->runId, $this->conversationId, 'CUSTOM', ['name' => 'tutor.final', 'value' => $final]);
            $log->finished($this->runId, $this->conversationId);
        } catch (Throwable $e) {
            report($e);
            $log->error($this->runId, $this->conversationId, 'The tutor could not answer. Please ask again.');
        }
    }

    /** The queue gave up on the job (timeout, worker killed). */
    public function failed(Throwable $e): void
    {
        app(RunLog::class)->error($this->runId, $this->conversationId, 'The tutor took too long to answer. Please ask again.', 'timeout');
    }
}
