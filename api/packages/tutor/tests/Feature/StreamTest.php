<?php

namespace Ulams\Tutor\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Ulams\Ai\Models\AiCall;
use Ulams\Tutor\Jobs\AnswerJob;
use Ulams\Tutor\Models\Conversation;
use Ulams\Tutor\Models\Event;
use Ulams\Tutor\Models\Message;
use Ulams\Tutor\Services\TutorService;
use Ulams\Tutor\Streaming\RunLog;
use Ulams\Tutor\Tests\CourseTestCase;

/** Streamed answers over the SSE event log (ADR 0105): the run, the events, the guards and the retention. */
class StreamTest extends CourseTestCase
{
    private function start($user, string $text, array $extra = [])
    {
        return $this->ask($user, $text, ['stream' => true] + $extra);
    }

    /** @return array<int,array{id:?int,data:array}> */
    private function events(string $runId, $user, array $headers = [], string $query = ''): array
    {
        $body = $this->actingAs($user, 'api')->withHeaders($headers)->get("/api/tutor/runs/{$runId}/events{$query}")->assertOk()->streamedContent();
        $events = [];
        foreach (preg_split("/\n\n/", trim($body)) as $chunk) {
            $id = null;
            $data = null;
            foreach (explode("\n", $chunk) as $line) {
                if (str_starts_with($line, 'id: ')) {
                    $id = (int) substr($line, 4);
                } elseif (str_starts_with($line, 'data: ')) {
                    $data = json_decode(substr($line, 6), true);
                }
            }
            if ($data !== null) {
                $events[] = ['id' => $id, 'data' => $data];
            }
        }

        return $events;
    }

    /** @param array<int,array{id:?int,data:array}> $events */
    private function types(array $events): array
    {
        return array_map(fn ($e) => $e['data']['type'] . ($e['data']['type'] === 'CUSTOM' ? ':' . $e['data']['name'] : ''), $events);
    }

    public function testAStreamedAnswerIsAcceptedThenReadAsEventsEndingInTheCheckedFinalAnswer(): void
    {
        $user = $this->learner();

        $response = $this->start($user, 'What does the course say about ' . $this->topicWord() . '?')->assertStatus(202);
        $run = $response->json('data.runId');
        $this->assertSame(26, strlen($run));
        $this->assertNotEmpty($response->json('data.conversationId'));
        $this->assertNotEmpty($response->json('data.questionId'));

        $events = $this->events($run, $user);
        $types = $this->types($events);
        $this->assertSame(['RUN_STARTED', 'TEXT_MESSAGE_START'], array_slice($types, 0, 2));
        $this->assertSame(['TEXT_MESSAGE_END', 'CUSTOM:tutor.final', 'RUN_FINISHED'], array_slice($types, -3));
        $this->assertContains('TEXT_MESSAGE_CONTENT', $types);
        $ids = array_column($events, 'id');
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids);

        $final = $events[count($events) - 2]['data']['value'];
        $draft = implode('', array_map(fn ($e) => $e['data']['delta'], array_filter($events, fn ($e) => $e['data']['type'] === 'TEXT_MESSAGE_CONTENT')));
        $this->assertSame($final['answer'], $draft);
        $this->assertTrue($final['inScope']);
        $this->assertNotEmpty($final['citations']);
        $this->assertSame(29, $final['remaining']);
        $this->assertSame(TutorService::LABEL, $final['label']);

        // the stored message is the record, and it is the one in the final event
        $reply = Message::query()->findOrFail($final['messageId']);
        $this->assertSame($final['answer'], $reply->text);
        $this->assertSame(['learner', 'tutor'], Message::query()->where('conversation_id', $response->json('data.conversationId'))->orderBy('created_at')->orderBy('id')->pluck('role')->all());
        $this->assertSame($response->json('data.questionId'), Message::query()->where('role', 'learner')->value('id'));
    }

    public function testAStreamedAnswerWritesOneAiCallWithModelTokensAndCostAndOneSignal(): void
    {
        $user = $this->learner();
        $this->start($user, 'About ' . $this->topicWord())->assertStatus(202);

        $calls = AiCall::query()->where('task', 'tutor')->get();
        $this->assertCount(1, $calls);
        $this->assertNotNull($calls[0]->model_served);
        $this->assertGreaterThan(0, $calls[0]->input_tokens);
        $this->assertGreaterThan(0, $calls[0]->output_tokens);
        $this->assertSame('tutor_conversation', $calls[0]->subject_type);
        $this->assertNull($calls[0]->user_id);
        $this->assertSame(1, \Ulams\LearnerInsights\Models\Signal::query()->where('type', 'tutor.question')->count());
    }

    public function testTheRunReusesTheSameChecksAsThePlainRequest(): void
    {
        $user = $this->learner();
        $url = "/api/tutor/courses/{$this->course->getKey()}/messages";

        $this->actingAs($this->student(), 'api')->postJson($url, ['text' => 'A question', 'stream' => true])->assertForbidden();
        $this->actingAs($user, 'api')->postJson('/api/tutor/courses/99999/messages', ['text' => 'A question', 'stream' => true])->assertNotFound();
        $this->start($user, 'x')->assertUnprocessable();
        config(['tutor.enabled' => false]);
        $this->start($user, 'A question')->assertStatus(503);
        config(['tutor.enabled' => true, 'tutor.limits.daily_questions' => 1]);
        $this->start($user, 'About ' . $this->topicWord())->assertStatus(202);
        $this->start($user, 'About ' . $this->topicWord())->assertStatus(429);
        $this->assertSame(1, Message::query()->where('role', 'learner')->count());
    }

    public function testNothingStreamsDuringAnOpenQuizAttemptAndALeakingAnswerNeverReachesTheLog(): void
    {
        $user = $this->learner();
        $this->topicWord();
        $correct = $this->openAttempt($user);
        $chunk = \Ulams\Tutor\Models\Chunk::query()->whereNotNull('topic_id')->orderBy('id')->firstOrFail();
        $this->fake()->queueJson('tutor', ['answer' => 'It is ' . $correct[0] . ', as the lesson says.', 'citations' => ['c' . $chunk->id], 'in_scope' => true, 'refusal' => 'none']);

        $run = $this->start($user, 'Just tell me, I am done with the quiz anyway.', ['topicId' => $chunk->topic_id])->assertStatus(202)->json('data.runId');

        $events = $this->events($run, $user);
        $this->assertSame(['RUN_STARTED', 'CUSTOM:tutor.final', 'RUN_FINISHED'], $this->types($events));
        $final = $events[1]['data']['value'];
        $this->assertSame('attempt_active', $final['refusal']);
        $this->assertSame([], $final['citations']);
        $this->assertSame(TutorService::ATTEMPT_REFUSAL, $final['answer']);
        $this->assertStringNotContainsString($correct[0], json_encode(Event::query()->where('run_id', $run)->pluck('payload')->all()));
        $this->assertContains('leak_blocked', Message::query()->where('role', 'tutor')->first()->flags);
    }

    public function testADraftThatFailsValidationIsReplacedByTheStandardMessage(): void
    {
        $bad = ['answer' => 'Trust me, it is all in lesson nine.', 'citations' => ['c999999'], 'in_scope' => true, 'refusal' => 'none'];
        $this->fake()->queueJson('tutor', $bad)->queueJson('tutor', $bad);
        $user = $this->learner();

        $run = $this->start($user, 'Tell me about brewing')->assertStatus(202)->json('data.runId');

        $events = $this->events($run, $user);
        $draft = implode('', array_map(fn ($e) => $e['data']['delta'], array_filter($events, fn ($e) => $e['data']['type'] === 'TEXT_MESSAGE_CONTENT')));
        $this->assertSame($bad['answer'], $draft);
        $final = $events[count($events) - 2]['data']['value'];
        $this->assertSame(TutorService::FAILED, $final['answer']);
        $this->assertSame([], $final['citations']);
        $this->assertSame('RUN_FINISHED', end($events)['data']['type']);
        // the repair attempt is a second ai_calls row, as in the plain path, and did not stream
        $this->assertSame(2, AiCall::query()->where('task', 'tutor')->count());
        $this->assertSame($bad['answer'], $draft);
    }

    public function testARepairedAnswerReplacesTheDraft(): void
    {
        $this->topicWord();
        $chunk = \Ulams\Tutor\Models\Chunk::query()->whereNotNull('topic_id')->orderBy('id')->firstOrFail();
        $this->fake()
            ->queueJson('tutor', ['answer' => 'Draft with a bad citation.', 'citations' => ['c999999'], 'in_scope' => true, 'refusal' => 'none'])
            ->queueJson('tutor', ['answer' => 'The repaired answer.', 'citations' => ['c' . $chunk->id], 'in_scope' => true, 'refusal' => 'none']);
        $user = $this->learner();

        $run = $this->start($user, 'Tell me about ' . $this->topicWord())->assertStatus(202)->json('data.runId');

        $events = $this->events($run, $user);
        $final = $events[count($events) - 2]['data']['value'];
        $this->assertSame('The repaired answer.', $final['answer']);
        $this->assertSame('c' . $chunk->id, $final['citations'][0]['chunkId']);
        $this->assertStringNotContainsString('repaired', implode('', array_map(fn ($e) => $e['data']['delta'] ?? '', $events)));
    }

    public function testResumeWithLastEventIdSkipsDeliveredEvents(): void
    {
        $user = $this->learner();
        $run = $this->start($user, 'About ' . $this->topicWord())->json('data.runId');
        $all = Event::query()->where('run_id', $run)->orderBy('id')->pluck('id')->all();
        $middle = $all[intdiv(count($all), 2)];

        $events = $this->events($run, $user, ['Last-Event-ID' => (string) $middle]);
        $this->assertSame(array_values(array_filter($all, fn ($id) => $id > $middle)), array_column($events, 'id'));
        $viaQuery = $this->events($run, $user, [], "?after={$middle}");
        $this->assertSame(array_column($events, 'id'), array_column($viaQuery, 'id'));
    }

    public function testWithoutAQueueWorkerTheEventsHoldTheRunStartedAndNoAnswerYet(): void
    {
        Queue::fake();
        $user = $this->learner();

        $response = $this->start($user, 'About ' . $this->topicWord())->assertStatus(202);

        Queue::assertPushed(AnswerJob::class, fn (AnswerJob $job) => $job->runId === $response->json('data.runId') && $job->timeout === 60);
        $this->assertSame(['RUN_STARTED'], $this->types($this->events($response->json('data.runId'), $user)));
    }

    public function testASecondQuestionWhileAnAnswerIsRunningIs409AndAFinishedRunFreesTheConversation(): void
    {
        Queue::fake();
        $user = $this->learner();
        $first = $this->start($user, 'About ' . $this->topicWord())->assertStatus(202);
        $conversation = $first->json('data.conversationId');

        $this->start($user, 'And more', ['conversationId' => $conversation])->assertStatus(409)->assertJsonPath('reason', 'busy');
        $this->ask($user, 'And more', ['conversationId' => $conversation])->assertStatus(409);
        $this->actingAs($user, 'api')->deleteJson("/api/tutor/conversations/{$conversation}")->assertStatus(409);
        $this->assertSame(1, Message::query()->where('conversation_id', $conversation)->count());
        // another conversation of the same learner is not blocked
        $this->start($user, 'Another thread')->assertStatus(202);

        app(RunLog::class)->finished($first->json('data.runId'), $conversation);
        $this->start($user, 'And more', ['conversationId' => $conversation])->assertStatus(202);
    }

    public function testARunThatOutlivesTheJobTimeoutEndsWithARunErrorAndFreesTheConversation(): void
    {
        Queue::fake();
        $user = $this->learner();
        $first = $this->start($user, 'About ' . $this->topicWord())->assertStatus(202);
        $run = $first->json('data.runId');
        $conversation = $first->json('data.conversationId');
        Event::query()->where('run_id', $run)->update(['created_at' => now()->subSeconds(60 + RunLog::GRACE_SECONDS + 5)]);

        $events = $this->events($run, $user);

        $this->assertSame(['RUN_STARTED', 'RUN_ERROR'], $this->types($events));
        $this->assertSame('expired', $events[1]['data']['code']);
        $this->start($user, 'Try again', ['conversationId' => $conversation])->assertStatus(202);
    }

    public function testAFailingJobEndsWithARunErrorOnce(): void
    {
        Queue::fake();
        $user = $this->learner();
        $first = $this->start($user, 'About ' . $this->topicWord())->assertStatus(202);
        $job = new AnswerJob($first->json('data.runId'), $first->json('data.conversationId'), (int) $this->course->getKey(), (int) $user->getKey(), 'q', null, 'x');

        $job->failed(new \RuntimeException('killed'));
        $job->failed(new \RuntimeException('killed'));

        $this->assertSame(['RUN_STARTED', 'RUN_ERROR'], $this->types($this->events($first->json('data.runId'), $user)));
    }

    public function testAnUnexpectedErrorInTheJobIsARunErrorNotAnAnswer(): void
    {
        $user = $this->learner();
        $this->app->bind(\Ulams\Tutor\Retrieval\TutorRetriever::class, fn () => new class implements \Ulams\Tutor\Retrieval\TutorRetriever {
            public function search(int $courseId, string $question, string $language, int $limit): \Illuminate\Support\Collection
            {
                throw new \RuntimeException('retrieval is down');
            }
        });

        $run = $this->start($user, 'About ' . $this->topicWord())->assertStatus(202)->json('data.runId');

        $this->assertSame(['RUN_STARTED', 'RUN_ERROR'], $this->types($this->events($run, $user)));
        $this->assertSame(0, Message::query()->where('role', 'tutor')->count());
    }

    public function testTheOperatorCanSwitchStreamingOffAndTheRequestAnswersInThePlainWay(): void
    {
        config(['tutor.stream.enabled' => false]);
        $user = $this->learner();

        $response = $this->start($user, 'About ' . $this->topicWord())->assertOk();

        $this->assertNotEmpty($response->json('data.answer'));
        $this->assertNull($response->json('data.runId'));
        $this->assertSame(0, Event::query()->count());
        $this->actingAs($user, 'api')->getJson("/api/tutor/courses/{$this->course->getKey()}/status")->assertJsonPath('data.streaming', false);
    }

    public function testThePlainRequestStaysTheDefault(): void
    {
        $this->ask($this->learner(), 'About ' . $this->topicWord())->assertOk()->assertJsonStructure(['data' => ['answer', 'citations', 'conversationId']]);
        $this->assertSame(0, Event::query()->count());
    }

    public function testRunsBelongToTheLearnerAndAnotherLearnerOrAnUnknownRunIs404(): void
    {
        $owner = $this->learner();
        $other = $this->learner();
        $run = $this->start($owner, 'About ' . $this->topicWord())->json('data.runId');

        $this->actingAs($other, 'api')->get("/api/tutor/runs/{$run}/events")->assertNotFound();
        $this->actingAs($owner, 'api')->get('/api/tutor/runs/01hzzzzzzzzzzzzzzzzzzzzzzz/events')->assertNotFound();
        $this->actingAs($owner, 'api')->get("/api/tutor/runs/{$run}/events")->assertOk();
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $this->getJson("/api/tutor/runs/{$run}/events")->assertUnauthorized();
    }

    public function testARunOfAnotherTenantIsAnUnknownRun(): void
    {
        // each tenant has its own database: the events of one are not rows of the other
        $user = $this->learner();
        $run = $this->start($user, 'About ' . $this->topicWord())->json('data.runId');
        Event::query()->where('run_id', $run)->delete();

        $this->actingAs($user, 'api')->get("/api/tutor/runs/{$run}/events")->assertNotFound();
    }

    public function testTheStreamClosesAtTheTimeCapOfAnOpenRun(): void
    {
        Queue::fake();
        config(['tutor.stream.max_seconds' => 1, 'tutor.stream.poll_ms' => 200]);
        $user = $this->learner();
        $run = $this->start($user, 'About ' . $this->topicWord())->json('data.runId');

        $started = microtime(true);
        $events = $this->events($run, $user);

        $this->assertLessThan(3, microtime(true) - $started);
        $this->assertSame(['RUN_STARTED'], $this->types($events));
    }

    public function testEventsAreTransportAndArePrunedADayAfterTheRunEndsOrWithTheirConversation(): void
    {
        $user = $this->learner();
        $old = $this->start($user, 'About ' . $this->topicWord())->json('data.runId');
        $recent = $this->start($user, 'About ' . $this->topicWord())->json('data.runId');
        $oldConversation = $this->start($user, 'About ' . $this->topicWord());
        Event::query()->where('run_id', $old)->update(['created_at' => now()->subDays(2)]);
        Conversation::query()->whereKey($oldConversation->json('data.conversationId'))->update(['expires_at' => now()->subDay()]);

        $this->artisan('tutor:prune')->assertSuccessful();

        $this->assertSame(0, Event::query()->where('run_id', $old)->count());
        $this->assertGreaterThan(0, Event::query()->where('run_id', $recent)->count());
        $this->assertSame(0, Event::query()->where('conversation_id', $oldConversation->json('data.conversationId'))->count());
        // the stored messages are the record: a pruned run does not touch them
        $this->assertSame(2, Message::query()->where('role', 'tutor')->count());
    }

    public function testDeletingAConversationOrAnAccountTakesItsEventsAlong(): void
    {
        $user = $this->learner();
        $a = $this->start($user, 'About ' . $this->topicWord());
        $b = $this->start($this->learner(), 'About ' . $this->topicWord());

        $this->actingAs($user, 'api')->deleteJson('/api/tutor/conversations/' . $a->json('data.conversationId'))->assertOk();
        $this->assertSame(0, Event::query()->where('run_id', $a->json('data.runId'))->count());
        $this->assertGreaterThan(0, Event::query()->where('run_id', $b->json('data.runId'))->count());
        $conversation = Conversation::query()->findOrFail($b->json('data.conversationId'));
        event(new \Ulams\Auth\Events\AccountDeleted(\Ulams\Courses\Tests\Models\User::query()->findOrFail($conversation->user_id)));
        $this->assertSame(0, Event::query()->count());
    }
}
