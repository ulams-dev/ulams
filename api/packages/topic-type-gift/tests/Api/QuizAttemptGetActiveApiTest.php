<?php

namespace EscolaLms\TopicTypeGift\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\TopicTypeGift\Events\QuizAttemptStartedEvent;
use EscolaLms\TopicTypeGift\Jobs\MarkAttemptAsEnded;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use EscolaLms\TopicTypeGift\Providers\SettingsServiceProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

class QuizAttemptGetActiveApiTest extends GiftQuestionTestCase
{
    use CreatesUsers;

    public function testGetActiveAttemptQuizAttemptUnauthorized(): void
    {
        $this->postJson('api/quiz-attempts', [
            'topic_gift_quiz_id' => $this->quiz->getKey(),
        ])
            ->assertUnauthorized();
    }

    public function testGetActiveQuizAttemptForbidden(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->postJson('api/quiz-attempts', [
                'topic_gift_quiz_id' => $this->quiz->getKey(),
            ])
            ->assertForbidden();
    }

    public function testCreateNewQuizAttempt(): void
    {
        Event::fake([QuizAttemptStartedEvent::class]);
        Queue::fake();

        $student = $this->makeStudent();
        $this->topic->course->users()->sync($student);

        $this->actingAs($student, 'api')
            ->postJson('api/quiz-attempts', [
                'topic_gift_quiz_id' => $this->quiz->getKey(),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('topic_gift_quiz_attempts', [
            'user_id' => $student->getKey(),
            'topic_gift_quiz_id' => $this->quiz->getKey(),
        ]);

        $attempt = QuizAttempt::query()->where('user_id', $student->getKey())->first();

        Queue::assertPushed(function (MarkAttemptAsEnded $job) use ($attempt) {
            $this->assertEquals($attempt->end_at->format('Y-m-d H:i'), $job->delay->format('Y-m-d H:i'));
            return true;
        });

        Event::assertDispatched(QuizAttemptStartedEvent::class);
    }

    public function testShouldNotCreateQuizAttemptWhenMaxNumberIsExceeded(): void
    {
        $this->quiz = GiftQuiz::factory()
            ->state(['max_attempts' => 2])
            ->create();
        $this->topic->topicable()->associate($this->quiz)->save();

        $student = $this->makeStudent();
        $this->topic->course->users()->sync($student);

        QuizAttempt::factory()
            ->count(2)
            ->state([
                'user_id' => $student->getKey(),
                'topic_gift_quiz_id' => $this->quiz->getKey(),
                'end_at' => Carbon::now()->subMinutes(2),
            ])->create();

        $this->actingAs($student, 'api')
            ->postJson('api/quiz-attempts', [
                'topic_gift_quiz_id' => $this->quiz->getKey(),
            ])
            ->assertStatus(400);

        $studentAttempts = QuizAttempt::query()->where('user_id', $student->getKey())->get();
        $this->assertCount(2, $studentAttempts);
    }

    public function testShouldReturnExistingActiveAttempt(): void
    {
        $student = $this->makeStudent();
        $this->topic->course->users()->sync($student);

        $attempt = QuizAttempt::factory()
            ->state([
                'user_id' => $student->getKey(),
                'topic_gift_quiz_id' => $this->quiz->getKey(),
            ])->create();

        $this->actingAs($student, 'api')
            ->postJson('api/quiz-attempts', [
                'topic_gift_quiz_id' => $this->quiz->getKey(),
            ])
            ->assertOk()
            ->assertJsonFragment([
                'id' => $attempt->getKey(),
            ]);

        $studentAttempts = QuizAttempt::query()->where('user_id', $student->getKey())->get();
        $this->assertCount(1, $studentAttempts);
    }

    public function testShouldSetDefaultAttemptEndTimeWhenQuizHasNoTimeSet(): void
    {
        Bus::fake();
        Config::set(SettingsServiceProvider::KEY . 'max_quiz_time', 123);

        $this->quiz = GiftQuiz::factory()->state(['max_execution_time' => null])->create();
        $this->topic->topicable()->associate($this->quiz)->save();
        $student = $this->makeStudent();
        $this->topic->course->users()->sync($student);

        $this->actingAs($student, 'api')
            ->postJson('api/quiz-attempts', [
                'topic_gift_quiz_id' => $this->quiz->getKey(),
            ])->assertCreated();

        $attempt = QuizAttempt::query()->where('user_id', $student->getKey())->first();
        $this->assertEquals(122, $attempt->end_at->diffInMinutes($attempt->start_at));
    }
}
