<?php

namespace Ulams\Tutor\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Dto\ContentBlock;
use Ulams\Ai\Dto\LlmRequest;
use Ulams\Ai\Exceptions\LlmException;
use Ulams\Ai\Models\AiCall;
use Ulams\Ai\Prompts\PromptRegistry;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Pipeline\PromptContext;
use Ulams\Courses\Models\Course;
use Ulams\LearnerInsights\Services\SignalRecorder;
use Ulams\LearnerInsights\Signals\SignalData;
use Ulams\LearnerInsights\Signals\SignalType;
use Ulams\Tutor\Jobs\AnswerJob;
use Ulams\Tutor\Models\Chunk;
use Ulams\Tutor\Models\Conversation;
use Ulams\Tutor\Models\Message;
use Ulams\Tutor\Retrieval\TutorRetriever;
use Ulams\Tutor\Streaming\RunLog;
use Ulams\Tutor\Support\Leak;

/**
 * Answers a learner's question from the course and nothing else (ADR 0060, plan 8.2). The request holds
 * the retrieved course chunks, the last messages of the conversation and the question: never the
 * learner's identity or history. The answer must cite the chunks it used; during a quiz attempt it must not
 * contain a correct option, and one that does is replaced by the standard refusal.
 */
class TutorService
{
    public const SUBJECT_TYPE = 'tutor_conversation';

    public const ATTEMPT_REFUSAL = "I can't help with quiz answers while a quiz attempt is open. Once you have submitted it, ask me again. Meanwhile I can explain the concepts from the lessons.";

    public const LABEL = 'AI tutor · answers only from this course';

    public const FAILED = "I couldn't answer that. Please try asking in a different way.";

    public function __construct(
        private readonly LlmClient $llm,
        private readonly PromptRegistry $prompts,
        private readonly TutorIndexer $indexer,
        private readonly TutorRetriever $retriever,
        private readonly AttemptGuard $guard,
        private readonly RunLog $runs,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config('tutor.enabled') && $this->llm->enabled();
    }

    public function remaining(int $userId): int
    {
        $used = (int) DB::table('tutor_daily_usage')->where('user_id', $userId)->where('date', now()->toDateString())->value('questions');

        return max(0, (int) config('tutor.limits.daily_questions') - $used);
    }

    /**
     * @return array{conversationId:string,messageId:string,questionId:string,answer:string,citations:array<int,array<string,mixed>>,refusal:string,inScope:bool,remaining:int}
     */
    public function ask(object $user, Course $course, string $question, ?int $topicId = null, ?string $conversationId = null): array
    {
        [$conversation, $learnerMessage] = $this->accept($user, $course, $question, $conversationId);

        return $this->answer($conversation, $course, (int) $user->getKey(), $question, $learnerMessage->id, $topicId) + ['questionId' => $learnerMessage->id];
    }

    public function streaming(): bool
    {
        return (bool) config('tutor.stream.enabled');
    }

    /**
     * Accepts a question and queues its answer (ADR 0105): the checks and the learner's message happen in the
     * request, RUN_STARTED is written with them, and `AnswerJob` writes the rest of the events.
     *
     * @return array{conversationId:string,runId:string,questionId:string}
     */
    public function start(object $user, Course $course, string $question, ?int $topicId = null, ?string $conversationId = null): array
    {
        $runId = RunLog::newRunId();
        [$conversation, $learnerMessage] = $this->accept($user, $course, $question, $conversationId, fn (Conversation $c) => $this->runs->started($runId, $c->id));
        AnswerJob::dispatchFor(new AnswerJob($runId, $conversation->id, (int) $course->getKey(), (int) $user->getKey(), $question, $topicId, $learnerMessage->id));

        return ['conversationId' => $conversation->id, 'runId' => $runId, 'questionId' => $learnerMessage->id];
    }

    /**
     * Everything the request itself checks and stores before an answer is written, for the plain and the
     * streamed path alike: the switch, course access, the limits, one running answer per conversation, the
     * conversation and the learner's message. Counts the question.
     *
     * @return array{0:Conversation,1:Message}
     */
    public function accept(object $user, Course $course, string $question, ?string $conversationId = null, ?callable $inTransaction = null): array
    {
        $userId = (int) $user->getKey();
        if (!$this->enabled()) {
            throw new TutorException(TutorException::DISABLED, 'The course tutor is not available.', 503);
        }
        if (!Gate::forUser($user)->allows('attend', $course)) {
            throw new TutorException(TutorException::FORBIDDEN, 'You cannot attend this course.', 403);
        }
        $this->assertWithinLimits($userId);

        return DB::transaction(function () use ($userId, $course, $question, $conversationId, $inTransaction) {
            $conversation = $conversationId
                ? Conversation::query()->where('user_id', $userId)->where('course_id', $course->getKey())->lockForUpdate()->findOrFail($conversationId)
                : Conversation::query()->create(['user_id' => $userId, 'course_id' => $course->getKey(), 'created_at' => now(), 'last_message_at' => now(), 'expires_at' => now()->addDays((int) config('tutor.retention_days'))]);
            if ($conversationId && $this->runs->openRun($conversation->id) !== null) {
                throw new TutorException(TutorException::BUSY, 'The tutor is still answering your last question.', 409);
            }
            $this->countQuestion($userId);
            $learnerMessage = Message::query()->create(['conversation_id' => $conversation->id, 'role' => 'learner', 'text' => $question, 'created_at' => now()]);
            if ($inTransaction !== null) {
                $inTransaction($conversation, $learnerMessage);
            }

            return [$conversation, $learnerMessage];
        });
    }

    /**
     * Retrieves, asks the model, checks the answer, stores the reply and records the signal. With `$onDelta` the
     * text of the answer is forwarded as the model writes it, except while a quiz attempt is open: then the
     * answer is generated whole and only released after the answer-leak check (ADR 0105).
     *
     * @param (callable(string):void)|null $onDelta
     *
     * @return array{conversationId:string,messageId:string,answer:string,citations:array<int,array<string,mixed>>,refusal:string,inScope:bool,remaining:int}
     */
    public function answer(Conversation $conversation, Course $course, int $userId, string $question, string $questionId, ?int $topicId = null, ?callable $onDelta = null): array
    {
        // the learner's message is already stored; the request carries it as the question
        $history = $conversation->messages()->get()->reject(fn (Message $m) => $m->id === $questionId)->slice(-6)->values();

        $this->indexer->ensureFresh((int) $course->getKey());
        $chunks = $this->retrieve($course, $question, $topicId);
        $attempt = $this->guard->check($userId, (int) $course->getKey());

        $flags = [];
        $citations = [];
        try {
            $request = $this->request($conversation, $chunks, $history, $question, $attempt['active']);
            $result = $onDelta !== null && !$attempt['active'] ? $this->llm->stream($request, $onDelta) : $this->llm->generate($request);
            $data = $result->data;
            $callId = $result->callIds[count($result->callIds) - 1] ?? null;
            $answer = $data['answer'];
            $refusal = $data['refusal'];
            $inScope = (bool) $data['in_scope'];
            $citations = $this->citations($chunks, $data['citations']);
            if ($attempt['active'] && Leak::detects($answer, $attempt['correct'])) {
                // never let a correct option out during an attempt
                $answer = self::ATTEMPT_REFUSAL;
                $refusal = 'attempt_active';
                $inScope = false;
                $citations = [];
                $flags[] = 'leak_blocked';
            }
        } catch (LlmException) {
            $answer = self::FAILED;
            $refusal = 'unsafe';
            $inScope = false;
            $callId = null;
            $flags[] = 'failed';
        }
        if ($attempt['active']) {
            $flags[] = 'attempt_active';
        }

        $reply = Message::query()->create([
            'conversation_id' => $conversation->id, 'role' => 'tutor', 'text' => $answer, 'citations' => $citations, 'refusal' => $refusal,
            'flags' => $flags, 'ai_call_id' => $callId, 'created_at' => now(),
        ]);
        $conversation->forceFill(['last_message_at' => now(), 'expires_at' => now()->addDays((int) config('tutor.retention_days'))])->save();
        $this->signal($userId, $course, $conversation, $reply);

        return [
            'conversationId' => $conversation->id, 'messageId' => $reply->id, 'answer' => $answer, 'citations' => $citations,
            'refusal' => $refusal, 'inScope' => $inScope, 'remaining' => $this->remaining($userId),
        ];
    }

    private function assertWithinLimits(int $userId): void
    {
        if ($this->remaining($userId) === 0) {
            throw new TutorException(TutorException::LIMIT, 'You have used today\'s questions. Try again tomorrow.', 429);
        }
        $month = CarbonImmutable::now()->startOfMonth();
        $spent = (int) AiCall::query()->where('subject_type', self::SUBJECT_TYPE)->where('created_at', '>=', $month)->sum('cost_micro_usd');
        if ($spent >= (int) round(((float) config('tutor.limits.tenant_monthly_usd')) * 1000000)) {
            throw new TutorException(TutorException::LIMIT, 'The tutor has reached its monthly limit.', 429);
        }
    }

    private function countQuestion(int $userId): void
    {
        DB::table('tutor_daily_usage')->upsert([['user_id' => $userId, 'date' => now()->toDateString(), 'questions' => 1]], ['user_id', 'date'], ['questions' => DB::raw('tutor_daily_usage.questions + 1')]);
    }

    /** @return \Illuminate\Support\Collection<int,Chunk> */
    private function retrieve(Course $course, string $question, ?int $topicId)
    {
        $found = $this->retriever->search((int) $course->getKey(), $question, (string) (Chunk::query()->where('course_id', $course->getKey())->value('language') ?? 'en'), (int) config('tutor.retrieval.top'));
        $current = $topicId ? Chunk::query()->where('course_id', $course->getKey())->where('topic_id', $topicId)->orderBy('id')->limit((int) config('tutor.retrieval.topic_chunks'))->get() : collect();
        $budget = (int) config('tutor.limits.context_chars');
        $used = 0;

        return $found->concat($current)->unique('id')->filter(function (Chunk $c) use (&$used, $budget) {
            $used += mb_strlen($c->text) + mb_strlen($c->label);

            return $used <= $budget;
        })->values();
    }

    /** @param \Illuminate\Support\Collection<int,Chunk> $chunks @param \Illuminate\Support\Collection<int,Message> $history */
    private function request(Conversation $conversation, $chunks, $history, string $question, bool $attemptActive): LlmRequest
    {
        $content = "<course_content untrusted=\"true\">\n";
        foreach ($chunks as $c) {
            $content .= '<chunk id="c' . $c->id . '" label="' . PromptContext::esc($c->label) . "\">\n" . PromptContext::esc($c->text) . "\n</chunk>\n";
        }
        $content .= '</course_content>';

        $turns = '';
        foreach ($history as $m) {
            $turns .= $m->role === 'learner'
                ? '<learner_message>' . PromptContext::esc($m->text) . "</learner_message>\n"
                : '<tutor_message>' . PromptContext::esc($m->text) . "</tutor_message>\n";
        }
        $known = $chunks->mapWithKeys(fn (Chunk $c) => ['c' . $c->id => true])->all();

        return new LlmRequest(
            task: 'tutor',
            prompt: $this->prompts->get('tutor', 'tutor'),
            blocks: [
                ContentBlock::text($content, true),
                ContentBlock::text($turns . '<mode>' . ($attemptActive ? 'attempt_active' : 'normal') . "</mode>\n<learner_message>" . PromptContext::esc($question) . '</learner_message>'),
            ],
            schema: (array) json_decode((string) file_get_contents(__DIR__ . '/../../resources/schemas/tutor.json'), true),
            subject: ['type' => self::SUBJECT_TYPE, 'id' => $conversation->id],
            userId: null,
            validator: fn (array $d) => $this->validate($d, $known),
        );
    }

    /** @param array<string,bool> $known @return string[] */
    private function validate(array $d, array $known): array
    {
        $errors = Checks::markup((string) ($d['answer'] ?? ''), 'answer');
        foreach ($d['citations'] ?? [] as $id) {
            if (!isset($known[$id])) {
                $errors[] = "citations: $id is not a chunk of the course content provided";
            }
        }
        if (($d['in_scope'] ?? false) && ($d['citations'] ?? []) === []) {
            $errors[] = 'an in-scope answer must cite at least one chunk';
        }
        if (($d['in_scope'] ?? false) && ($d['refusal'] ?? 'none') !== 'none') {
            $errors[] = 'an in-scope answer has refusal none';
        }
        if (!($d['in_scope'] ?? false) && ($d['refusal'] ?? 'none') === 'none') {
            $errors[] = 'an answer that is not in scope must say why in refusal';
        }

        return $errors;
    }

    /** @param \Illuminate\Support\Collection<int,Chunk> $chunks @param string[] $ids @return array<int,array<string,mixed>> */
    private function citations($chunks, array $ids): array
    {
        $byId = $chunks->keyBy(fn (Chunk $c) => 'c' . $c->id);

        return array_values(array_map(fn ($id) => ['chunkId' => $id, 'label' => $byId[$id]->label, 'topicId' => $byId[$id]->topic_id, 'fragmentIds' => $byId[$id]->fragment_ids ?? []], array_filter($ids, fn ($id) => isset($byId[$id]))));
    }

    private function signal(int $userId, Course $course, Conversation $conversation, Message $reply): void
    {
        if (!class_exists(SignalRecorder::class)) {
            return;
        }
        app(SignalRecorder::class)->queue(new SignalData($userId, SignalType::TUTOR_QUESTION, 'tutor', 'tutor:' . $reply->id, CarbonImmutable::now(), (int) $course->getKey(), null, null, null, ['conversation_id' => $conversation->id]));
    }
}
