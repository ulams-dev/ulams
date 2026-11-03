<?php

namespace Ulams\LearnerInsights\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Topic;
use Ulams\LearnerInsights\Jobs\BackfillJob;
use Ulams\LearnerInsights\Services\SignalRecorder;
use Ulams\LearnerInsights\Signals\SignalData;
use Ulams\LearnerInsights\Signals\SignalType;
use Ulams\LearnerInsights\Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    private Course $course;

    private Topic $topic;

    private int $n = 0;

    /** 12 learners: six fail question 3 twice, six answer it correctly; four of them finish the course. */
    private function cohort(int $size = 12): array
    {
        [$this->course, $this->topic] = $this->courseWithTopic();
        $users = [];
        for ($i = 0; $i < $size; $i++) {
            $user = $this->learner($this->course);
            $users[] = $user;
            $this->signal($user, SignalType::TOPIC_OPENED, null, null, ['revisit' => false]);
            $struggler = $i < intdiv($size, 2);
            foreach ($struggler ? [false, false] : [true] as $correct) {
                $this->signal($user, SignalType::QUIZ_ANSWER, 3, $correct ? 1.0 : 0.0, ['question_id' => 3, 'correct' => $correct]);
            }
            $this->signal($user, SignalType::QUIZ_ATTEMPT_FINISHED, null, $struggler ? 0.2 : 0.9, ['passed' => !$struggler, 'attempt_no' => 1]);
        }
        \Illuminate\Support\Facades\DB::table('course_user')->whereIn('user_id', array_map(fn ($u) => $u->getKey(), array_slice($users, 6, 4)))->update(['finished' => true]);

        return $users;
    }

    private function signal($user, string $type, ?int $question, ?float $value, array $data): void
    {
        app(SignalRecorder::class)->record(new SignalData($user->getKey(), $type, 'gift', 'an:' . ++$this->n, now(), $this->course->getKey(), $this->topic->getKey(), $question, $value, $data));
    }

    private function url(string $path): string
    {
        return "/api/admin/learner-insights/courses/{$this->course->getKey()}/$path";
    }

    public function testTheSummaryShowsProgressQuizPerformanceAndStatuses(): void
    {
        $this->cohort();

        $data = $this->actingAs($this->admin(), 'api')->getJson($this->url('summary'))->assertOk()->json('data');

        $this->assertFalse($data['smallGroup']);
        $this->assertSame(12, $data['learners']['enrolled']);
        $this->assertSame(12, $data['learners']['active']);
        $this->assertSame(4, $data['learners']['completed']);
        $this->assertSame(0.3333, $data['learners']['completionRate']);
        $this->assertSame(12, $data['quiz']['attempts']);
        $this->assertSame(0.55, $data['quiz']['averageScore']);
        $this->assertSame(0.5, $data['quiz']['passRate']);
        $this->assertSame(['on_track' => 6, 'struggling' => 6, 'at_risk' => 0], $data['statuses']);
    }

    public function testTheSectionsThatConfusePeopleAreRankedWithTheirReasons(): void
    {
        $this->cohort();

        $data = $this->actingAs($this->admin(), 'api')->getJson($this->url('elements?period=7'))->assertOk()->json('data');

        $this->assertSame(7, $data['periodDays']);
        $question = collect($data['elements'])->firstWhere('elementKey', 'question:3');
        $this->assertFalse($question['hidden']);
        $this->assertSame(12, $question['activeLearners']);
        $this->assertSame(0.5, $question['struggleRate']);
        $this->assertSame([['rule' => 'failed_attempts', 'count' => 6]], $question['topReasons']);
        $this->assertSame('question:3', $data['elements'][0]['elementKey'], 'the most confusing element comes first');
    }

    public function testSmallGroupsAreWithheldOnTheServer(): void
    {
        $this->cohort(6);
        $admin = $this->admin();

        $summary = $this->actingAs($admin, 'api')->getJson($this->url('summary'))->assertOk()->json('data');
        $this->assertTrue($summary['smallGroup']);
        $this->assertSame(['courseId', 'periodDays', 'minGroupSize', 'enrolled', 'smallGroup'], array_keys($summary));

        $elements = $this->actingAs($admin, 'api')->getJson($this->url('elements'))->assertOk()->json('data.elements');
        $this->assertNotEmpty($elements);
        foreach ($elements as $element) {
            $this->assertTrue($element['hidden']);
            $this->assertArrayNotHasKey('struggleRate', $element);
            $this->assertArrayNotHasKey('activeLearners', $element);
        }
    }

    public function testAuthorsSeeAggregatesButOtherUsersDoNot(): void
    {
        $this->cohort();
        $author = $this->tutor();
        $this->course->authors()->attach($author->getKey());

        $this->actingAs($author, 'api')->getJson($this->url('summary'))->assertOk();
        $this->actingAs($author, 'api')->getJson($this->url('elements'))->assertOk();
        $this->actingAs($this->tutor(), 'api')->getJson($this->url('summary'))->assertForbidden();
        $this->actingAs($this->student(), 'api')->getJson($this->url('elements'))->assertForbidden();
        $this->actingAs($this->admin(), 'api')->getJson('/api/admin/learner-insights/courses/99999/summary')->assertNotFound();
        $this->actingAs($this->admin(), 'api')->getJson($this->url('summary?period=45'))->assertStatus(422);
        Auth::forgetGuards();
        $this->getJson($this->url('summary'))->assertUnauthorized();
    }

    public function testIndividualStatusesNeedTheirOwnPermissionOrTheTenantSetting(): void
    {
        $users = $this->cohort();
        $author = $this->tutor();
        $this->course->authors()->attach($author->getKey());

        $list = $this->actingAs($this->admin(), 'api')->getJson($this->url('learners?status=struggling'))->assertOk()->json('data');
        $this->assertCount(6, $list);
        $this->assertStringContainsString('2 failed attempts', $list[0]['reasons'][0]);
        $this->assertSame('struggling', $list[0]['status']);

        $this->actingAs($author, 'api')->getJson($this->url('learners'))->assertForbidden();
        config(['learner_insights.instructors_see_individuals' => true]);
        $this->actingAs($author, 'api')->getJson($this->url('learners'))->assertOk();
        $this->actingAs($this->tutor(), 'api')->getJson($this->url('learners'))->assertForbidden();
        $this->actingAs($this->admin(), 'api')->getJson($this->url('learners?status=bogus'))->assertStatus(422);

        // the timeline stays admin-only even with the setting
        $url = "/api/admin/learner-insights/learners/{$users[0]->getKey()}/courses/{$this->course->getKey()}/timeline";
        $this->actingAs($author, 'api')->getJson($url)->assertForbidden();
        $timeline = $this->actingAs($this->admin(), 'api')->getJson($url)->assertOk()->json('data');
        $this->assertSame('quiz.attempt_finished', $timeline[0]['type'] ?? $timeline[1]['type']);
        $this->assertGreaterThanOrEqual(4, count($timeline));
    }

    public function testBackfillIsQueuedForManagersOnly(): void
    {
        Bus::fake([BackfillJob::class]);
        [$course] = $this->courseWithTopic();

        $this->actingAs($this->admin(), 'api')->postJson('/api/admin/learner-insights/backfill', ['courseId' => $course->getKey()])->assertStatus(202);
        Bus::assertDispatched(BackfillJob::class, fn (BackfillJob $j) => $j->courseId === $course->getKey());
        $this->actingAs($this->tutor(), 'api')->postJson('/api/admin/learner-insights/backfill')->assertForbidden();
        $this->actingAs($this->admin(), 'api')->postJson('/api/admin/learner-insights/backfill', ['courseId' => 99999])->assertNotFound();
    }

    public function testAnAdminTokenOfAnotherTenantIsRejected(): void
    {
        $this->cohort();
        $headers = ['Authorization' => 'Bearer ' . $this->admin()->createToken('test')->accessToken];
        $this->getJson($this->url('summary'), $headers)->assertOk();
        Auth::forgetGuards();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        config(['passport.private_key' => $private, 'passport.public_key' => openssl_pkey_get_details($key)['key']]);
        foreach ([AuthorizationServer::class, ResourceServer::class, AccessTokenRepository::class] as $service) {
            $this->app->forgetInstance($service);
        }
        Auth::forgetGuards();

        $this->getJson($this->url('summary'), $headers)->assertUnauthorized();
        $this->getJson($this->url('learners'), $headers)->assertUnauthorized();
    }
}
