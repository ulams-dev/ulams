<?php

namespace Ulams\LearnerInsights\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ulams\Auth\Models\UserSetting;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Events\CourseStarted;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\LearnerInsights\Models\Signal;
use Ulams\LearnerInsights\Services\SignalRecorder;
use Ulams\LearnerInsights\Signals\SignalData;
use Ulams\LearnerInsights\Signals\SignalType;
use Ulams\LearnerInsights\Tests\TestCase;

class SignalRecordingTest extends TestCase
{
    public function testProgressWritesOpenedProgressedAndCompletedSignals(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);

        $this->progress($topic, $user, ProgressStatus::IN_PROGRESS, 30);
        $this->progress($topic, $user, ProgressStatus::IN_PROGRESS, 90);
        $this->progress($topic, $user, ProgressStatus::COMPLETE, 120);

        $types = Signal::query()->where('user_id', $user->getKey())->pluck('type')->countBy()->all();
        $this->assertSame(3, $types[SignalType::TOPIC_PROGRESSED]);
        $this->assertSame(1, $types[SignalType::TOPIC_OPENED], 'one "opened" per topic and day');
        $this->assertSame(1, $types[SignalType::TOPIC_COMPLETED]);

        $progressed = Signal::query()->where('type', SignalType::TOPIC_PROGRESSED)->orderBy('id')->pluck('value')->all();
        $this->assertSame([30.0, 60.0, 30.0], $progressed);

        $completed = Signal::query()->where('type', SignalType::TOPIC_COMPLETED)->first();
        $this->assertSame($course->getKey(), $completed->course_id);
        $this->assertSame('topic:' . $topic->getKey(), $completed->element_key, 'a course not built with the builder keeps the topic key');
        $this->assertNull($completed->element_id);
    }

    public function testRevisitIsFlaggedWhenTheTopicWasCompletedBefore(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);
        $this->finish($topic, $user, 50);
        $this->travelTo(now()->addDay());

        $this->progress($topic, $user, ProgressStatus::IN_PROGRESS, 60);
        $this->travelBack();

        $opened = Signal::query()->where('type', SignalType::TOPIC_OPENED)->orderByDesc('id')->firstOrFail();
        $this->assertTrue($opened->data['revisit']);
    }

    public function testBuilderElementsReplaceTheTopicKey(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);
        $element = strtolower((string) Str::ulid());
        $session = strtolower((string) Str::ulid());
        EntityMapEntry::query()->create(['session_id' => $session, 'element_id' => $element, 'entity_type' => 'topic', 'entity_id' => $topic->getKey()]);

        $this->finish($topic, $user, 10);

        $signal = Signal::query()->where('type', SignalType::TOPIC_COMPLETED)->firstOrFail();
        $this->assertSame($element, $signal->element_key);
        $this->assertSame($element, $signal->element_id);
        $this->assertSame($session, $signal->session_id);
    }

    public function testCourseEventsAreRecordedOnce(): void
    {
        [$course] = $this->courseWithTopic();
        $user = $this->learner($course);

        event(new CourseStarted($user, $course));
        event(new CourseStarted($user, $course));
        event(new CourseFinished($user, $course));

        $this->assertSame(1, Signal::query()->where('type', SignalType::COURSE_STARTED)->count());
        $this->assertSame(1, Signal::query()->where('type', SignalType::COURSE_COMPLETED)->count());
    }

    public function testNothingIsRecordedWhileTheModuleIsOff(): void
    {
        config(['learner_insights.enabled' => false]);
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);

        $this->finish($topic, $user, 10);

        $this->assertSame(0, Signal::query()->count());
    }

    public function testAnOptedOutLearnerProducesNoSignals(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        $optedOut = $this->learner($course);
        $other = $this->learner($course);
        UserSetting::query()->create(['user_id' => $optedOut->getKey(), 'key' => 'insights.enabled', 'value' => 'false']);

        $this->finish($topic, $optedOut, 10);
        $this->finish($topic, $other, 10);

        $this->assertSame(0, Signal::query()->where('user_id', $optedOut->getKey())->count());
        $this->assertGreaterThan(0, Signal::query()->where('user_id', $other->getKey())->count());
    }

    public function testConsentModeRecordsNothingUntilTheLearnerConsents(): void
    {
        config(['learner_insights.require_consent' => true]);
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);

        $this->progress($topic, $user, ProgressStatus::IN_PROGRESS, 10);
        $this->assertSame(0, Signal::query()->count());

        UserSetting::query()->create(['user_id' => $user->getKey(), 'key' => 'insights.consent', 'value' => now()->toIso8601String()]);
        $this->progress($topic, $user, ProgressStatus::IN_PROGRESS, 20);
        $this->assertGreaterThan(0, Signal::query()->count());
    }

    public function testRecordingIsIdempotentAndDataIsWhitelisted(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);
        $recorder = app(SignalRecorder::class);
        $signal = new SignalData($user->getKey(), SignalType::QUIZ_ANSWER, 'gift', 'gift_answer:1', now(), null, $topic->getKey(), 9, 0.0, ['question_id' => 9, 'correct' => false, 'free_text' => 'secret']);

        $this->assertTrue($recorder->record($signal));
        $this->assertFalse($recorder->record($signal));

        $row = Signal::query()->firstOrFail();
        $this->assertSame(['question_id' => 9, 'correct' => false], $row->data);
        $this->assertSame('question:9', $row->element_key);
        $this->assertSame($course->getKey(), $row->course_id);
    }

    public function testUnknownTypesAndSourcesAreRejected(): void
    {
        $recorder = app(SignalRecorder::class);
        $this->expectException(\InvalidArgumentException::class);
        $recorder->record(new SignalData(1, 'quiz.free_text', 'gift', 'x', now()));
    }

    public function testTheTableIsAppendOnlyOnPostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('The UPDATE trigger is PostgreSQL only.');
        }
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);
        app(SignalRecorder::class)->record(new SignalData($user->getKey(), SignalType::SESSION_LOGIN, 'auth', 'login:1', now()));

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('learner_signals')->update(['value' => 1]);
    }
}
