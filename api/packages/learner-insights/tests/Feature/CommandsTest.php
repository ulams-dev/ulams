<?php

namespace Ulams\LearnerInsights\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\LearnerInsights\Models\Signal;
use Ulams\LearnerInsights\Services\SignalRecorder;
use Ulams\LearnerInsights\Signals\SignalData;
use Ulams\LearnerInsights\Signals\SignalType;
use Ulams\LearnerInsights\Tests\TestCase;

class CommandsTest extends TestCase
{
    public function testBackfillBuildsSignalsFromExistingProgressAndIsIdempotent(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);
        // progress written while the module was off
        config(['learner_insights.enabled' => false]);
        $this->finish($topic, $user, 45);
        config(['learner_insights.enabled' => true]);
        $this->assertSame(0, Signal::query()->count());

        $this->artisan('learner-insights:backfill', ['--course' => $course->getKey()])->assertSuccessful();
        $first = Signal::query()->count();
        $this->artisan('learner-insights:backfill', ['--course' => $course->getKey()])->assertSuccessful();

        $this->assertGreaterThanOrEqual(3, $first, 'progressed, completed and course started');
        $this->assertSame($first, Signal::query()->count(), 'a re-run adds nothing');
        $this->assertSame(['backfill'], Signal::query()->pluck('source')->unique()->values()->all());
        $this->assertTrue(Signal::query()->where('type', SignalType::TOPIC_COMPLETED)->where('topic_id', $topic->getKey())->exists());
    }

    public function testBackfillDryRunWritesNothingAndOptedOutLearnersAreSkipped(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);
        config(['learner_insights.enabled' => false]);
        $this->finish($topic, $user, 45);
        config(['learner_insights.enabled' => true]);

        $this->artisan('learner-insights:backfill', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Signal::query()->count());

        \Ulams\Auth\Models\UserSetting::query()->create(['user_id' => $user->getKey(), 'key' => 'insights.enabled', 'value' => '0']);
        $this->artisan('learner-insights:backfill')->assertSuccessful();
        $this->assertSame(0, Signal::query()->count());
    }

    public function testBackfillIsANoOpWhileTheModuleIsOff(): void
    {
        config(['learner_insights.enabled' => false]);
        $this->artisan('learner-insights:backfill')->expectsOutputToContain('disabled')->assertSuccessful();
    }

    public function testPruneDeletesOnlySignalsOlderThanTheRetentionWindow(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);
        $recorder = app(SignalRecorder::class);
        config(['learner_insights.retention.signals_days' => 30]);
        $recorder->record(new SignalData($user->getKey(), SignalType::SESSION_LOGIN, 'auth', 'old', now()->subDays(40)));
        $recorder->record(new SignalData($user->getKey(), SignalType::SESSION_LOGIN, 'auth', 'new', now()->subDays(5)));

        $this->artisan('learner-insights:prune', ['--chunk' => 1])->assertSuccessful();

        $this->assertSame(['new'], Signal::query()->pluck('source_ref')->all());
    }

    public function testTimelinePrintsTheJourney(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);
        $this->finish($topic, $user, 45);

        $this->artisan('learner-insights:timeline', ['--user' => $user->getKey(), '--course' => $course->getKey()])
            ->expectsOutputToContain('topic.completed')
            ->assertSuccessful();
        $this->artisan('learner-insights:timeline')->assertFailed();
    }
}
