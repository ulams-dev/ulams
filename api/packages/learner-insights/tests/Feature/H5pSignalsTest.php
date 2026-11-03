<?php

namespace Ulams\LearnerInsights\Tests\Feature;

use Ulams\Courses\Repositories\Contracts\CourseH5PProgressRepositoryContract;
use Ulams\LearnerInsights\Models\Signal;
use Ulams\LearnerInsights\Tests\TestCase;

class H5pSignalsTest extends TestCase
{
    public function testAnH5pResultBecomesASignalWithTheScaledScoreAndTheVerb(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);

        app(CourseH5PProgressRepositoryContract::class)->store($topic, $user, 'completed', ['result' => ['score' => ['scaled' => 0.4, 'raw' => 4], 'response' => 'free text']]);

        $signal = Signal::query()->where('type', 'h5p.result')->firstOrFail();
        $this->assertSame(0.4, $signal->value);
        $this->assertSame(['verb' => 'completed'], $signal->data, 'the statement itself is never stored');
        $this->assertSame($topic->getKey(), $signal->topic_id);
        $this->assertSame($course->getKey(), $signal->course_id);
        $this->assertSame('h5p', $signal->source);
    }

    public function testAResultWithoutAScoreHasNoValueAndAnOutOfRangeScoreIsClamped(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);
        $repo = app(CourseH5PProgressRepositoryContract::class);

        $repo->store($topic, $user, 'attempted', ['result' => ['completion' => true]]);
        $repo->store($topic, $user, 'answered', ['result' => ['score' => ['scaled' => 7]]]);

        $this->assertNull(Signal::query()->where('data->verb', 'attempted')->firstOrFail()->value);
        $this->assertSame(1.0, Signal::query()->where('data->verb', 'answered')->firstOrFail()->value);
    }

    public function testBackfillReadsExistingH5pResults(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);
        config(['learner_insights.enabled' => false]);
        app(CourseH5PProgressRepositoryContract::class)->store($topic, $user, 'completed', ['result' => ['score' => ['scaled' => 0.9]]]);
        config(['learner_insights.enabled' => true]);
        $this->assertSame(0, Signal::query()->count());

        $this->artisan('learner-insights:backfill')->assertSuccessful();
        $this->artisan('learner-insights:backfill')->assertSuccessful();

        $this->assertSame(1, Signal::query()->where('type', 'h5p.result')->count());
        $this->assertSame(0.9, Signal::query()->where('type', 'h5p.result')->value('value'));
    }

    public function testNothingIsRecordedForAnOptedOutLearner(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);
        \Ulams\Auth\Models\UserSetting::query()->create(['user_id' => $user->getKey(), 'key' => 'insights.enabled', 'value' => 'false']);

        app(CourseH5PProgressRepositoryContract::class)->store($topic, $user, 'completed', ['result' => ['score' => ['scaled' => 1]]]);

        $this->assertSame(0, Signal::query()->count());
    }
}
