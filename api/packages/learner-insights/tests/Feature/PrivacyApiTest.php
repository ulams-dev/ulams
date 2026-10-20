<?php

namespace Ulams\LearnerInsights\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Ulams\Auth\Events\AccountDeleted;
use Ulams\Auth\Models\UserSetting;
use Ulams\LearnerInsights\Models\LearnerStatus;
use Ulams\LearnerInsights\Models\Signal;
use Ulams\LearnerInsights\Tests\Support\Journey;
use Ulams\LearnerInsights\Tests\TestCase;

class PrivacyApiTest extends TestCase
{
    /** Two learners; the first fails question 3 twice and so is struggling. */
    private function twoLearners(): array
    {
        [$course, $topic] = $this->courseWithTopic();
        $a = Journey::learner($this, $course, ['quiz' => $topic]);
        $b = Journey::learner($this, $course, ['quiz' => $topic]);
        $a->day(1)->answer('quiz', 3, false)->answer('quiz', 3, false);
        $b->answer('quiz', 4, true);

        return [$course, $a, $b];
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function testALearnerSeesOnlyTheirOwnStatusWithReasons(): void
    {
        [$course, $a, $b] = $this->twoLearners();

        $own = $this->actingAs($a->user, 'api')->getJson('/api/learner-insights/me')->assertOk();
        $own->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.courses.0.courseId', $course->getKey())
            ->assertJsonPath('data.courses.0.status', 'struggling')
            ->assertJsonPath('data.courses.0.elements.0.elementKey', 'question:3')
            ->assertJsonPath('data.settings.enabled', true)
            ->assertJsonPath('data.retention.signalsDays', 180);
        $this->assertStringContainsString('2 failed attempts on', $own->json('data.courses.0.reasons.0.message'));

        $other = $this->actingAs($b->user, 'api')->getJson('/api/learner-insights/me')->assertOk();
        $this->assertSame(['on_track'], array_column($other->json('data.courses'), 'status'), 'the other learner is on track and sees nothing of the first learner');
        $this->assertSame([], $other->json('data.courses.0.elements'));
        $this->assertSame([], $other->json('data.courses.0.reasons'));
    }

    public function testOptingOutStopsRecordingAndCanEraseWhatWasRecorded(): void
    {
        [$course, $a] = $this->twoLearners();
        $id = $a->user->getKey();
        $this->assertGreaterThan(0, Signal::query()->where('user_id', $id)->count());

        $this->actingAs($a->user, 'api')->putJson('/api/learner-insights/me/settings', ['enabled' => false, 'eraseExisting' => true])
            ->assertOk()->assertJsonPath('data.enabled', false);
        $this->assertSame(0, Signal::query()->where('user_id', $id)->count());
        $this->assertSame(0, LearnerStatus::query()->where('user_id', $id)->count());

        $a->answer('quiz', 3, false)->answer('quiz', 3, false);
        $this->assertSame(0, Signal::query()->where('user_id', $id)->count(), 'nothing new is recorded after the opt-out');

        $this->actingAs($a->user, 'api')->putJson('/api/learner-insights/me/settings', ['enabled' => true, 'nudges' => false, 'help' => false])
            ->assertOk()->assertJsonPath('data.nudges', false)->assertJsonPath('data.help', false)->assertJsonPath('data.enabled', true);
    }

    public function testConsentIsGivenAndWithdrawn(): void
    {
        config(['learner_insights.require_consent' => true]);
        [$course, $topic] = $this->courseWithTopic();
        $user = $this->learner($course);

        $this->actingAs($user, 'api')->putJson('/api/learner-insights/me/settings', ['consent' => true])->assertOk();
        $this->assertNotNull($this->actingAs($user, 'api')->getJson('/api/learner-insights/me')->json('data.settings.consent'));

        $this->actingAs($user, 'api')->putJson('/api/learner-insights/me/settings', ['consent' => false])->assertOk()->assertJsonPath('data.consent', null);
        $this->assertFalse(UserSetting::query()->where('user_id', $user->getKey())->where('key', 'insights.consent')->exists());
    }

    public function testTheExportHoldsOnlyTheCallersDataAndIsAudited(): void
    {
        [, $a, $b] = $this->twoLearners();

        $response = $this->actingAs($a->user, 'api')->get('/api/learner-insights/me/export')->assertOk();
        $data = json_decode($response->streamedContent(), true);

        $this->assertSame($a->user->getKey(), $data['userId']);
        $this->assertSame([$a->user->getKey()], array_values(array_unique(array_column($data['learner_signals'], 'user_id'))));
        $this->assertSame('failed_attempts', $data['learner_statuses'][0]['reasons'][0]['rule']);
        $this->assertNotEmpty($data['learner_status_transitions']);

        $audit = DB::table('learner_insights_audit')->where('action', 'export')->first();
        $this->assertSame($a->user->getKey(), (int) $audit->subject_user_id);
        $this->assertStringNotContainsString('failed_attempts', $audit->counts, 'the audit holds counts, not content');
    }

    public function testErasingRemovesTheCallersDataOnly(): void
    {
        [, $a, $b] = $this->twoLearners();
        $bBefore = Signal::query()->where('user_id', $b->user->getKey())->count();
        $this->actingAs($a->user, 'api')->putJson('/api/learner-insights/me/settings', ['enabled' => false]);

        $this->actingAs($a->user, 'api')->deleteJson('/api/learner-insights/me')->assertOk()->assertJsonPath('data.deleted.learner_statuses', 2);

        $this->assertSame(0, Signal::query()->where('user_id', $a->user->getKey())->count());
        $this->assertSame($bBefore, Signal::query()->where('user_id', $b->user->getKey())->count());
        $this->assertSame('false', UserSetting::query()->where('user_id', $a->user->getKey())->where('key', 'insights.enabled')->value('value'), 'the opt-out stays');
        $this->assertTrue(DB::table('learner_insights_audit')->where('action', 'erase')->exists());
    }

    public function testADeletedAccountIsErasedImmediately(): void
    {
        [, $a, $b] = $this->twoLearners();

        event(new AccountDeleted($a->user));

        $this->assertSame(0, Signal::query()->where('user_id', $a->user->getKey())->count());
        $this->assertSame(0, LearnerStatus::query()->where('user_id', $a->user->getKey())->count());
        $this->assertGreaterThan(0, Signal::query()->where('user_id', $b->user->getKey())->count());
        $this->assertSame('account_deleted', DB::table('learner_insights_audit')->value('action'));
    }

    public function testPruneDropsOldHistoriesAndLapsedStatuses(): void
    {
        [$course, $a] = $this->twoLearners();
        config(['learner_insights.retention.history_days' => 30]);
        DB::table('learner_status_transitions')->update(['occurred_at' => now()->subDays(40)]);
        // still enrolled: its status survives; once the course is finished it lapses
        DB::table('learner_statuses')->update(['evaluated_at' => now()->subDays(40)]);

        $this->artisan('learner-insights:prune')->assertSuccessful();
        $this->assertSame(0, DB::table('learner_status_transitions')->count());
        $this->assertSame(3, DB::table('learner_statuses')->count());

        DB::table('course_user')->where('user_id', $a->user->getKey())->update(['finished' => true]);
        $this->artisan('learner-insights:prune')->assertSuccessful();
        $this->assertSame(0, DB::table('learner_statuses')->where('user_id', $a->user->getKey())->count());
    }

    public function testAnonymousCallersAndOtherTenantsAreRejected(): void
    {
        [, $a] = $this->twoLearners();
        $this->getJson('/api/learner-insights/me')->assertUnauthorized();
        $this->deleteJson('/api/learner-insights/me')->assertUnauthorized();

        $token = $a->user->createToken('test')->accessToken;
        $headers = ['Authorization' => 'Bearer ' . $token];
        $this->getJson('/api/learner-insights/me', $headers)->assertOk();
        Auth::forgetGuards();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        config(['passport.private_key' => $private, 'passport.public_key' => openssl_pkey_get_details($key)['key']]);
        foreach ([AuthorizationServer::class, ResourceServer::class, AccessTokenRepository::class] as $service) {
            $this->app->forgetInstance($service);
        }
        Auth::forgetGuards();

        $this->getJson('/api/learner-insights/me', $headers)->assertUnauthorized();
        $this->getJson('/api/learner-insights/me/export', $headers)->assertUnauthorized();
    }
}
