<?php

namespace Ulams\LearnerInsights\Tests\Feature;

use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\Courses\Models\Course;
use Ulams\LearnerInsights\Models\Signal;
use Ulams\LearnerInsights\Services\ChosenOptions;
use Ulams\LearnerInsights\Signals\SignalType;
use Ulams\LearnerInsights\Tests\TestCase;
use Ulams\TopicTypeGift\Events\QuizAttemptFinishedEvent;
use Ulams\TopicTypeGift\Models\AttemptAnswer;
use Ulams\TopicTypeGift\Models\GiftQuestion;
use Ulams\TopicTypeGift\Models\QuizAttempt;

/**
 * The options a learner chose are recorded as the option ids of the course content (ADR 0181, Q4), so the
 * author can be told which wrong answer is popular, aggregated and above the minimum group size.
 */
class ChosenOptionsTest extends TestCase
{
    private int $question;

    private array $options;

    protected function setUp(): void
    {
        parent::setUp();
        $session = $this->toApplied($this->author());
        Course::query()->findOrFail($session->course_id)->update(['status' => 'published']);
        $entry = EntityMapEntry::query()->where('session_id', $session->id)->where('entity_type', 'gift_question')->orderBy('id')->firstOrFail();
        $this->question = (int) $entry->entity_id;
        $this->options = Blueprint::find($session->currentVersion->document, $entry->element_id)['node']['options'];
    }

    private function wrong(): array
    {
        return collect($this->options)->firstWhere('correct', false);
    }

    public function testTheTextOfTheChosenOptionBecomesItsContentId(): void
    {
        $chosen = app(ChosenOptions::class);
        $wrong = $this->wrong();

        $this->assertSame([$wrong['id']], $chosen->forAnswer($this->question, ['text' => $wrong['text']]));
        $this->assertSame([$wrong['id']], $chosen->forAnswer($this->question, ['text' => '  ' . strtoupper($wrong['text']) . "\n"]), 'case and spacing do not matter');
        $both = collect($this->options)->take(2)->all();
        $this->assertEqualsCanonicalizing(array_column($both, 'id'), $chosen->forAnswer($this->question, ['multiple' => array_column($both, 'text')]));
    }

    public function testFreeTextUnknownAnswersAndQuestionsOutsideTheBuilderRecordNothing(): void
    {
        $chosen = app(ChosenOptions::class);

        $this->assertSame([], $chosen->forAnswer($this->question, ['text' => 'something nobody offered']));
        $this->assertSame([], $chosen->forAnswer($this->question, ['numeric' => 3]));
        $this->assertSame([], $chosen->forAnswer($this->question, null));
        $this->assertSame([], $chosen->forAnswer($this->question, []));
        $this->assertSame([], $chosen->forAnswer(99999999, ['text' => $this->wrong()['text']]), 'a question that was not built with the Course Builder');
    }

    public function testAFinishedAttemptRecordsTheChosenIdsWithTheAnswerSignal(): void
    {
        $user = $this->student();
        $quiz = GiftQuestion::query()->findOrFail($this->question)->topic_gift_quiz_id;
        $attempt = QuizAttempt::query()->create(['topic_gift_quiz_id' => $quiz, 'user_id' => $user->getKey(), 'started_at' => now()->subMinute(), 'end_at' => now()]);
        AttemptAnswer::query()->create(['topic_gift_quiz_attempt_id' => $attempt->getKey(), 'topic_gift_question_id' => $this->question, 'answer' => ['text' => $this->wrong()['text']], 'score' => 0, 'graded_at' => now()]);

        event(new QuizAttemptFinishedEvent($user, $attempt));

        $signal = Signal::query()->where('type', SignalType::QUIZ_ANSWER)->where('user_id', $user->getKey())->sole();
        $this->assertFalse($signal->data['correct']);
        $this->assertSame([$this->wrong()['id']], $signal->data['chosen']);
        // ids of the author's own content, never the learner's words
        $this->assertStringNotContainsString($this->wrong()['text'], json_encode($signal->data));
    }
}
