<?php

namespace Ulams\LearnerInsights\Tests\Scenario;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Ulams\Auth\Models\UserSetting;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\LearnerInsights\Events\LearnerAtRisk;
use Ulams\LearnerInsights\Events\LearnerRecovered;
use Ulams\LearnerInsights\Events\LearnerStruggling;
use Ulams\LearnerInsights\Models\LearnerStatus;
use Ulams\LearnerInsights\Models\Signal;
use Ulams\LearnerInsights\Tests\Support\Journey;
use Ulams\LearnerInsights\Tests\TestCase;

/** Synthetic learners walk through a course; the whole chain from signal to status to event runs. */
class JourneyTest extends TestCase
{
    private function course(): array
    {
        [$course, $first] = $this->courseWithTopic();
        $lesson = Lesson::query()->find($first->lesson_id);
        $second = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true]);
        $quiz = Topic::factory()->create(['lesson_id' => $lesson->getKey(), 'active' => true]);

        return [$course, ['t1' => $first, 't2' => $second, 'quiz' => $quiz]];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([LearnerStruggling::class, LearnerAtRisk::class, LearnerRecovered::class]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function testASteadyLearnerIsNeverFlagged(): void
    {
        [$course, $topics] = $this->course();
        $j = Journey::learner($this, $course, $topics);

        $j->day(1)->open('t1')->spend('t1', 300)->complete('t1')->nightly()
            ->day(2)->open('t2')->spend('t2', 280)->complete('t2')->nightly()
            ->day(3)->answer('quiz', 11, true)->passQuiz('quiz')->nightly()
            ->day(5)->nightly();

        $j->assertCourse('on_track')->assertEvent(LearnerStruggling::class, 0)->assertEvent(LearnerAtRisk::class, 0);
        $this->assertSame(0, DB::table('learner_status_transitions')->count());
    }

    public function testAQuizStrugglerIsFlaggedWithAReasonAndRecovers(): void
    {
        [$course, $topics] = $this->course();
        $j = Journey::learner($this, $course, $topics);

        $j->day(1)->complete('t1')->answer('quiz', 3, false)->assertElement('question:3', null)->assertCourse('on_track');
        $j->day(2)->answer('quiz', 3, false);

        $j->assertElement('question:3', 'struggling')->assertCourse('struggling')->assertEvent(LearnerStruggling::class);
        $row = LearnerStatus::query()->where('element_key', 'question:3')->firstOrFail();
        $this->assertSame('failed_attempts', $row->reasons[0]['rule']);
        $this->assertStringContainsString('2 failed attempts on', $row->messages('en')[0]);

        // a third wrong answer does not fire the event again
        $j->day(3)->answer('quiz', 3, false)->assertEvent(LearnerStruggling::class);

        $j->day(4)->answer('quiz', 3, true);
        $j->assertElement('question:3', null)->assertCourse('on_track')->assertEvent(LearnerRecovered::class, 2);
        $history = DB::table('learner_status_transitions')->orderBy('id')->get()->map(fn ($t) => ($t->element_key ?: 'course') . ': ' . $t->from . '>' . $t->to)->all();
        $this->assertSame(['question:3: on_track>struggling', 'course: on_track>struggling', 'question:3: struggling>on_track', 'course: struggling>on_track'], $history);
    }

    public function testADropOutIsAtRiskOnceAndRecoversWhenTheyReturn(): void
    {
        [$course, $topics] = $this->course();
        $j = Journey::learner($this, $course, $topics);

        $j->day(1)->open('t1')->spend('t1', 200)->complete('t1');
        $j->day(7)->nightly()->assertCourse('on_track');
        $j->day(8)->nightly()->assertCourse('at_risk')->assertEvent(LearnerAtRisk::class);
        $j->day(9)->nightly()->day(10)->nightly()->assertCourse('at_risk')->assertEvent(LearnerAtRisk::class);

        $reason = LearnerStatus::query()->where('element_key', '')->firstOrFail();
        $this->assertSame('inactivity', $reason->reasons[0]['rule']);
        // the reason is refreshed on every evaluation while the status holds
        $this->assertSame(['days' => 9], $reason->reasons[0]['params']);
        $this->assertStringContainsString('9 days without activity', $reason->messages('en')[0]);

        $j->day(12)->open('t2');
        $j->assertCourse('on_track')->assertEvent(LearnerRecovered::class);
    }

    public function testSlowReadingBelowTheCohortMinimumIsNotFlagged(): void
    {
        [$course, $topics] = $this->course();
        $key = 'topic:' . $topics['t1']->getKey();
        DB::table('learner_insights_element_stats')->insert(['course_id' => $course->getKey(), 'element_key' => $key, 'median_seconds' => 100, 'learners' => 9, 'computed_at' => now()]);
        $j = Journey::learner($this, $course, $topics);

        $j->day(1)->open('t1')->spend('t1', 5000)->assertElement($key, null)->assertCourse('on_track');

        // with a cohort of 10 the same learner is flagged
        DB::table('learner_insights_element_stats')->update(['learners' => 10]);
        $j->day(1)->spend('t1', 10)->assertElement($key, 'struggling');
    }

    public function testAnOptedOutLearnerHasNoSignalsAndNoStatus(): void
    {
        [$course, $topics] = $this->course();
        $user = $this->learnerFor($course);
        UserSetting::query()->create(['user_id' => $user->getKey(), 'key' => 'insights.enabled', 'value' => 'false']);
        $j = Journey::learner($this, $course, $topics, $user);

        $j->day(1)->answer('quiz', 3, false)->day(2)->answer('quiz', 3, false)->day(20)->nightly();

        $this->assertSame(0, Signal::query()->count());
        $this->assertSame(0, LearnerStatus::query()->count());
        $j->assertEvent(LearnerStruggling::class, 0)->assertEvent(LearnerAtRisk::class, 0);
    }

    public function testConsentModeStartsRecordingOnlyAfterConsent(): void
    {
        config(['learner_insights.require_consent' => true]);
        [$course, $topics] = $this->course();
        $user = $this->learnerFor($course);
        $j = Journey::learner($this, $course, $topics, $user);

        $j->day(1)->answer('quiz', 3, false)->answer('quiz', 3, false)->assertCourse('on_track');
        $this->assertSame(0, Signal::query()->count());

        UserSetting::query()->create(['user_id' => $user->getKey(), 'key' => 'insights.consent', 'value' => '2026-11-21']);
        $j->day(2)->answer('quiz', 3, false)->answer('quiz', 3, false);
        $j->assertElement('question:3', 'struggling');
    }
}
