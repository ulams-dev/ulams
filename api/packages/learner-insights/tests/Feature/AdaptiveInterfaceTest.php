<?php

namespace Ulams\LearnerInsights\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Ulams\Auth\Models\UserSetting;
use Ulams\Courses\Models\Topic;
use Ulams\LearnerInsights\Tests\Support\Journey;
use Ulams\LearnerInsights\Tests\TestCase;

class AdaptiveInterfaceTest extends TestCase
{
    private function course(): array
    {
        [$course, $topic] = $this->courseWithTopic();

        return [$course, ['quiz' => $topic, 't2' => Topic::factory()->create(['lesson_id' => $topic->lesson_id, 'active' => true])]];
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function show($user, $course)
    {
        return $this->actingAs($user, 'api')->getJson("/api/learner-insights/courses/{$course->getKey()}/presentation");
    }

    public function testTheProfileStaysDefaultWhileTheFlagIsOff(): void
    {
        [$course, $topics] = $this->course();
        $j = Journey::learner($this, $course, $topics);
        $j->day(1)->answer('quiz', 1, false)->answer('quiz', 1, false)->answer('quiz', 2, false)->answer('quiz', 2, false);

        $data = $this->show($j->user, $course)->assertOk()->json('data');

        $this->assertFalse($data['adaptive']);
        $this->assertSame(['density' => 'comfortable', 'chunking' => 'whole', 'navigation' => 'linear', 'hints' => 0], $data['profile']);
        $this->assertSame([], $data['why']);
    }

    public function testAStrugglingLearnerGetsStepsAndHintsWithTheReasonWhy(): void
    {
        config(['learner_insights.adaptive_interface' => true]);
        [$course, $topics] = $this->course();
        $j = Journey::learner($this, $course, $topics);
        $j->day(1)->answer('quiz', 1, false)->answer('quiz', 1, false)->answer('quiz', 2, false)->answer('quiz', 2, false);

        $data = $this->show($j->user, $course)->assertOk()->json('data');

        $this->assertTrue($data['adaptive']);
        $this->assertSame('steps', $data['profile']['chunking']);
        $this->assertSame(2, $data['profile']['hints']);
        $this->assertNotEmpty($data['why']);
        $this->assertSame('steps', $data['derived']['chunking']);
    }

    public function testASteadyFastLearnerGetsACompactLayoutAndAnAtRiskOneKeepsItLinear(): void
    {
        config(['learner_insights.adaptive_interface' => true]);
        [$course, $topics] = $this->course();
        $fast = Journey::learner($this, $course, $topics);
        foreach (range(1, 5) as $i) {
            $fast->day($i)->complete($i % 2 ? 'quiz' : 't2');
        }
        $data = $this->show($fast->user, $course)->json('data');
        $this->assertSame('compact', $data['profile']['density']);
        $this->assertSame('overview', $data['profile']['navigation']);

        $away = Journey::learner($this, $course, $topics);
        $away->day(1)->open('quiz')->day(9)->nightly();
        $data = $this->show($away->user, $course)->json('data');
        $this->assertSame(3, $data['profile']['hints']);
        $this->assertSame('linear', $data['profile']['navigation']);
    }

    public function testTheLearnerOverrideAlwaysWinsAndCanBeRemoved(): void
    {
        config(['learner_insights.adaptive_interface' => true]);
        [$course, $topics] = $this->course();
        $j = Journey::learner($this, $course, $topics);
        $j->day(1)->answer('quiz', 1, false)->answer('quiz', 1, false)->answer('quiz', 2, false)->answer('quiz', 2, false);
        $url = "/api/learner-insights/courses/{$course->getKey()}/presentation";

        $this->actingAs($j->user, 'api')->putJson($url, ['chunking' => 'whole'])->assertOk()
            ->assertJsonPath('data.profile.chunking', 'whole')->assertJsonPath('data.derived.chunking', 'steps')->assertJsonPath('data.overrides.chunking', 'whole');
        $this->show($j->user, $course)->assertJsonPath('data.profile.chunking', 'whole');

        $this->actingAs($j->user, 'api')->putJson($url, ['chunking' => null])->assertOk()->assertJsonPath('data.profile.chunking', 'steps');
        $this->assertSame(0, UserSetting::query()->where('user_id', $j->user->getKey())->where('key', 'like', 'insights.presentation.%')->count());

        $this->actingAs($j->user, 'api')->putJson($url, ['chunking' => 'paragraphs'])->assertStatus(422);
        $this->actingAs($j->user, 'api')->putJson($url, ['hints' => 7])->assertStatus(422);
    }

    public function testOptedOutLearnersAndOutsidersGetNothingAdaptive(): void
    {
        config(['learner_insights.adaptive_interface' => true]);
        [$course, $topics] = $this->course();
        $user = $this->learnerFor($course);
        UserSetting::query()->create(['user_id' => $user->getKey(), 'key' => 'insights.enabled', 'value' => 'false']);
        $this->show($user, $course)->assertOk()->assertJsonPath('data.adaptive', false);

        $this->actingAs($this->student(), 'api')->getJson("/api/learner-insights/courses/{$course->getKey()}/presentation")->assertForbidden();
        Auth::forgetGuards();
        $this->getJson("/api/learner-insights/courses/{$course->getKey()}/presentation")->assertUnauthorized();
    }
}
