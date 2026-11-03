<?php

namespace Ulams\LearnerInsights\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Ulams\Auth\Models\UserSetting;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\LearnerInsights\Events\LearnerNudged;
use Ulams\LearnerInsights\Models\Intervention;
use Ulams\LearnerInsights\Models\Signal;
use Ulams\LearnerInsights\Services\NudgeService;
use Ulams\LearnerInsights\Services\OutcomeTracker;
use Ulams\LearnerInsights\Tests\Support\Journey;
use Ulams\LearnerInsights\Tests\TestCase;

class NudgeTest extends TestCase
{
    private function course(): array
    {
        [$course, $first] = $this->courseWithTopic();
        $second = Topic::factory()->create(['lesson_id' => $first->lesson_id, 'active' => true]);

        return [$course, ['t1' => $first, 't2' => $second]];
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    /** A learner who stopped after day 1 and is at risk on day 8. */
    private function dropOut(): Journey
    {
        [$course, $topics] = $this->course();
        $j = Journey::learner($this, $course, $topics);
        $j->day(1)->open('t1')->spend('t1', 100)->complete('t1');

        return $j;
    }

    public function testAnAtRiskLearnerGetsOneNudgeWithReasonAndContinueLink(): void
    {
        Event::fake([LearnerNudged::class]);
        $j = $this->dropOut();

        $j->day(8)->nightly();

        Event::assertDispatchedTimes(LearnerNudged::class, 1);
        Event::assertDispatched(LearnerNudged::class, function (LearnerNudged $e) use ($j) {
            $data = $e->toArray();
            $this->assertSame($j->user->getKey(), $e->getUser()->getKey());
            $this->assertSame($j->course->title, $data['courseTitle']);
            $this->assertStringContainsString('7 days without activity', $data['reasonText']);
            $this->assertStringContainsString('/learn/' . $j->course->getKey() . '/', $data['continueUrl']);
            $this->assertStringContainsString('?n=' . $data['nudgeId'] . '.', $data['continueUrl']);
            // only scalars travel with the notification
            foreach (array_diff_key($data, ['user' => 1]) as $value) {
                $this->assertIsScalar($value);
            }

            return true;
        });
        $nudge = Intervention::query()->firstOrFail();
        $this->assertSame('pending', $nudge->outcome);
        $this->assertSame(1, Signal::query()->where('type', 'nudge.sent')->count());
    }

    public function testNextTopicInTheLinkIsTheFirstUnfinishedOne(): void
    {
        [$course, $topics] = $this->course();
        $topics['t1']->update(['order' => 1]);
        $topics['t2']->update(['order' => 2]);
        $user = $this->learner($course);
        $service = app(NudgeService::class);

        $this->assertStringContainsString('/learn/' . $course->getKey() . '/' . $topics['t1']->getKey() . '?n=', $service->continueUrl($user->getKey(), $course->getKey(), 'abc'));

        $this->finish($topics['t1'], $user, 30);
        $this->assertStringContainsString('/learn/' . $course->getKey() . '/' . $topics['t2']->getKey() . '?n=', $service->continueUrl($user->getKey(), $course->getKey(), 'abc'), 'topic 1 is finished, so the link opens topic 2');
    }

    public function testASecondTriggerTheSameWeekIsSuppressedAndLogged(): void
    {
        Event::fake([LearnerNudged::class]);
        $j = $this->dropOut();
        $j->day(8)->nightly();

        app(NudgeService::class)->consider($j->user->getKey(), $j->course->getKey(), [['rule' => 'inactivity', 'messageKey' => 'insights.reason.inactivity', 'params' => ['days' => 9]]]);

        Event::assertDispatchedTimes(LearnerNudged::class, 1);
        $suppressed = Intervention::query()->where('outcome', 'suppressed')->firstOrFail();
        $this->assertSame('rate_limit_course', $suppressed->detail);

        // a week later the learner may be nudged again
        $j->day(16);
        app(NudgeService::class)->consider($j->user->getKey(), $j->course->getKey(), []);
        Event::assertDispatchedTimes(LearnerNudged::class, 2);
    }

    public function testAtMostTwoNudgesPerLearnerAndWeekAcrossCourses(): void
    {
        Event::fake([LearnerNudged::class]);
        $user = null;
        $service = app(NudgeService::class);
        foreach ([1, 2, 3] as $i) {
            [$course] = $this->courseWithTopic();
            $user ??= $this->learner($course);
            $user->courses()->syncWithoutDetaching([$course->getKey()]);
            $service->consider($user->getKey(), $course->getKey(), []);
        }

        Event::assertDispatchedTimes(LearnerNudged::class, 2);
        $this->assertSame('rate_limit_learner', Intervention::query()->where('outcome', 'suppressed')->value('detail'));
    }

    public function testPreferencesAndTenantSwitchesAreRespected(): void
    {
        Event::fake([LearnerNudged::class]);
        $j = $this->dropOut();
        UserSetting::query()->create(['user_id' => $j->user->getKey(), 'key' => 'insights.nudges', 'value' => 'false']);

        $j->day(8)->nightly();
        Event::assertNotDispatched(LearnerNudged::class);
        $this->assertSame('learner_preference', Intervention::query()->value('detail'));

        // the tenant switch: nothing is sent and nothing is logged
        Intervention::query()->delete();
        UserSetting::query()->delete();
        config(['learner_insights.nudges.enabled' => false]);
        $j->day(9)->nightly();
        Event::assertNotDispatched(LearnerNudged::class);
        $this->assertSame(0, Intervention::query()->count());
    }

    public function testAFinishedCourseIsNotNudged(): void
    {
        Event::fake([LearnerNudged::class]);
        $j = $this->dropOut();
        DB::table('course_user')->where('user_id', $j->user->getKey())->update(['finished' => true]);

        app(NudgeService::class)->consider($j->user->getKey(), $j->course->getKey(), []);

        Event::assertNotDispatched(LearnerNudged::class);
        $this->assertSame('not_active', Intervention::query()->value('detail'));
    }

    public function testTheOpenedLinkIsRecordedOnceForTheRightLearnerOnly(): void
    {
        Event::fake([LearnerNudged::class]);
        $j = $this->dropOut();
        $j->day(8)->nightly();
        $token = app(NudgeService::class)->sign(Intervention::query()->value('id'));
        $other = $this->learnerFor($j->course);

        $this->actingAs($j->user, 'api')->postJson('/api/learner-insights/nudges/opened', ['n' => $token])->assertStatus(202);
        $this->actingAs($j->user, 'api')->postJson('/api/learner-insights/nudges/opened', ['n' => $token])->assertStatus(202);
        $this->assertSame(1, Signal::query()->where('type', 'nudge.opened')->count());

        $this->actingAs($other, 'api')->postJson('/api/learner-insights/nudges/opened', ['n' => $token])->assertNotFound();
        $this->actingAs($j->user, 'api')->postJson('/api/learner-insights/nudges/opened', ['n' => Intervention::query()->value('id') . '.forged'])->assertNotFound();
        Auth::forgetGuards();
        $this->postJson('/api/learner-insights/nudges/opened', ['n' => $token])->assertUnauthorized();
    }

    public function testRecoveryIsTrackedAndExpiredNudgesCountAsNotRecovered(): void
    {
        Event::fake([LearnerNudged::class]);
        $j = $this->dropOut();
        $j->day(8)->nightly();
        $this->assertSame('pending', Intervention::query()->orderBy('id')->value('outcome'));

        // the learner returns and finishes the next topic
        $j->day(9)->open('t2')->complete('t2');
        $this->assertSame('recovered', Intervention::query()->orderBy('id')->value('outcome'));
        $this->assertSame(['recovered' => 1, 'closed' => 1, 'rate' => 1.0], app(OutcomeTracker::class)->rate($j->course->getKey(), 'nudge'));

        // a second learner never comes back: closed after the 14-day window
        $second = Journey::learner($this, $j->course, ['t1' => Topic::query()->find($j->topicId('t1'))]);
        $second->day(20)->open('t1')->nightly();
        $second->day(28)->nightly();
        $this->assertSame(1, Intervention::query()->where('user_id', $second->user->getKey())->where('outcome', 'pending')->count());
        $second->day(43)->nightly();

        // The sweep runs before the expiry in the same nightly run, so on day 43 the still at-risk learner is nudged
        // again (a new pending row) while the day-28 nudge is closed. Pick the first nudge explicitly: an unordered
        // value() returns whichever tuple Postgres scans first, which changes with the page's dead-tuple layout.
        $firstNudge = Intervention::query()->where('user_id', $second->user->getKey())->where('kind', Intervention::NUDGE)->where('outcome', '!=', 'suppressed')->orderBy('id')->first();
        $this->assertSame('not_recovered', $firstNudge->outcome);
        $rate = app(OutcomeTracker::class)->rate($j->course->getKey(), 'nudge');
        $closed = Intervention::query()->whereIn('outcome', ['recovered', 'not_recovered'])->count();
        $recovered = Intervention::query()->where('outcome', 'recovered')->count();
        $this->assertSame($closed, $rate['closed']);
        $this->assertSame(round($recovered / $closed, 4), $rate['rate']);
        $this->assertGreaterThan(0.0, $rate['rate']);
        $this->assertLessThan(1.0, $rate['rate']);
    }
}
