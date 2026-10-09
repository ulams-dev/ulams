<?php

namespace Ulams\Lrs\Tests\API;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Ulams\Lrs\Events\AuCompletionReported;
use Ulams\Lrs\Extensions\SessionToken;
use Ulams\Lrs\Models\LaunchToken;
use Ulams\Lrs\Services\Contracts\LrsServiceContract;
use Ulams\Lrs\Tests\TestCase;
use Ulams\Lrs\Tests\Traits\XapiTesting;

/**
 * The cmi5 launch token and the LRS-only session token (ADR 0046).
 */
class Cmi5LaunchTokenTest extends TestCase
{
    use DatabaseTransactions, XapiTesting;

    private $learner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpXapi();
        $this->learner = config('auth.providers.users.model')::factory()->create();
        $this->learner->guard_name = 'api';
        $this->learner->assignRole('student');
    }

    /** @return array{0: array, 1: string} the launch params and the one-time token */
    private function launch(?int $auId = 7): array
    {
        $this->actingAs($this->learner, 'api');
        $params = app(LrsServiceContract::class)->launchParams(null, null, $auId);
        parse_str((string) parse_url($params['fetch'], PHP_URL_QUERY), $query);

        return [$params, $query['token']];
    }

    private function sessionToken(string $oneTime): string
    {
        return $this->postJson('/api/cmi5/fetch?token=' . $oneTime)->assertOk()->json('auth-token');
    }

    private function asSession(string $token): array
    {
        return ['Authorization' => 'Basic ' . $token];
    }

    public function test_the_launch_url_carries_a_one_time_token_and_not_the_learners_access_token(): void
    {
        $passport = $this->learner->createToken('learner')->accessToken;
        [$params, $oneTime] = $this->launch();

        $this->assertStringNotContainsString($passport, $params['url']);
        $this->assertStringNotContainsString('eyJ', $params['url'], 'no JWT may appear in the launch URL');
        $this->assertSame(64, strlen($oneTime));
        // only the hash is stored
        $this->assertDatabaseMissing('lrs_launch_tokens', ['token_hash' => $oneTime]);
        $this->assertDatabaseHas('lrs_launch_tokens', [
            'token_hash' => LaunchToken::hash($oneTime),
            'user_id' => $this->learner->getKey(),
            'registration' => $params['registration'],
            'au_id' => 7,
            'access_uuid' => strtolower($this->access->uuid),
        ]);
    }

    public function test_fetch_exchanges_the_launch_token_for_an_lrs_only_token(): void
    {
        [, $oneTime] = $this->launch();

        $token = $this->sessionToken($oneTime);

        $this->assertStringStartsWith(SessionToken::PREFIX, $token);
        $this->assertNotNull(LaunchToken::query()->where('token_hash', LaunchToken::hash($oneTime))->first()->used_at);
        // an AU that fetches again within the session gets the same token
        $this->assertSame($token, $this->sessionToken($oneTime));
    }

    public function test_fetch_refuses_unknown_empty_and_expired_launch_tokens(): void
    {
        $this->postJson('/api/cmi5/fetch?token=' . Str::random(64))->assertUnauthorized()->assertJsonStructure(['error-code', 'error-text']);
        $this->postJson('/api/cmi5/fetch')->assertUnauthorized();
        $this->postJson('/api/cmi5/fetch?token=' . Str::random(500))->assertUnauthorized();

        [, $oneTime] = $this->launch();
        $this->travel(11)->minutes(); // the launch window is 10 minutes
        $this->postJson('/api/cmi5/fetch?token=' . $oneTime)->assertUnauthorized();
    }

    public function test_the_session_ends_after_the_configured_minutes(): void
    {
        config(['ulams_lrs.session_minutes' => 30]);
        [$params, $oneTime] = $this->launch();
        $token = $this->sessionToken($oneTime);
        $statement = $this->statement(['context' => ['registration' => $params['registration']]]);

        $this->xapi('POST', '/statements', $statement, $this->asSession($token))->assertOk();

        $this->travel(29)->minutes();
        $this->xapi('POST', '/statements', $this->statement(['context' => ['registration' => $params['registration']]]), $this->asSession($token))->assertOk();
        $this->assertSame($token, $this->sessionToken($oneTime));

        $this->travel(2)->minutes();
        $this->xapi('POST', '/statements', $this->statement(['context' => ['registration' => $params['registration']]]), $this->asSession($token))->assertUnauthorized();
        $this->postJson('/api/cmi5/fetch?token=' . $oneTime)->assertUnauthorized();
    }

    public function test_the_session_token_is_rejected_by_the_rest_of_the_api(): void
    {
        [, $oneTime] = $this->launch();
        $token = $this->sessionToken($oneTime);
        $this->app['auth']->forgetGuards(); // launch() acted as the learner; from here on only the token counts

        $this->flushHeaders()->withToken($token)->getJson('/api/cmi5/courses/1')->assertUnauthorized();
        $this->flushHeaders()->withHeaders($this->asSession($token))->getJson('/api/admin/cmi5/statements')->assertUnauthorized();
    }

    public function test_a_tampered_or_forged_session_token_is_rejected(): void
    {
        [$params, $oneTime] = $this->launch();
        $token = $this->sessionToken($oneTime);
        [, $payload, $signature] = explode('.', $token);
        $body = $this->statement(['context' => ['registration' => $params['registration']]]);

        // another learner id with the old signature
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $claims['u'] = $claims['u'] + 1;
        $forged = SessionToken::PREFIX . rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=') . '.' . $signature;
        $this->xapi('POST', '/statements', $body, $this->asSession($forged))->assertUnauthorized();

        // a token for a launch that does not exist
        $unknown = SessionToken::issue(999999, $this->learner->getKey(), $params['registration'], 7, $this->access->uuid, time() + 600);
        $this->xapi('POST', '/statements', $body, $this->asSession($unknown))->assertUnauthorized();

        // a token whose launch was never fetched has no session
        [$other] = $this->launch();
        $row = LaunchToken::query()->where('registration', $other['registration'])->first();
        $unused = SessionToken::issue($row->getKey(), $this->learner->getKey(), $other['registration'], 7, $this->access->uuid, time() + 600);
        $this->xapi('POST', '/statements', $body, $this->asSession($unused))->assertUnauthorized();

        // a token of another tenant (other APP_KEY)
        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);
        $this->xapi('POST', '/statements', $body, $this->asSession($token))->assertUnauthorized();
    }

    public function test_a_session_is_bound_to_its_xapi_access(): void
    {
        [$params, $oneTime] = $this->launch();
        $token = $this->sessionToken($oneTime);
        $body = $this->statement(['context' => ['registration' => $params['registration']]]);

        $this->xapi('POST', '/statements', $body, $this->asSession($token))->assertOk();

        LaunchToken::query()->update(['access_uuid' => (string) Str::uuid()]);
        $this->xapi('POST', '/statements', $body, $this->asSession($this->sessionToken($oneTime)))->assertUnauthorized();
    }

    public function test_a_session_writes_and_reads_only_its_own_registration(): void
    {
        [$params, $oneTime] = $this->launch();
        $mine = $params['registration'];
        $token = $this->sessionToken($oneTime);
        $session = $this->asSession($token);

        // another registration, or none, is refused
        $this->xapi('POST', '/statements', $this->statement(['context' => ['registration' => (string) Str::uuid()]]), $session)->assertForbidden();
        $this->xapi('POST', '/statements', $this->statement(), $session)->assertForbidden();
        $this->xapi('POST', '/statements', [$this->statement(['context' => ['registration' => $mine]]), $this->statement()], $session)->assertForbidden();
        $id = (string) Str::uuid();
        $this->xapi('PUT', '/statements?statementId=' . $id, $this->statement(['context' => ['registration' => (string) Str::uuid()]]), $session)->assertForbidden();

        // someone else's statement exists in the store
        $foreign = (string) Str::uuid();
        $this->xapi('POST', '/statements', $this->statement(['id' => $foreign, 'context' => ['registration' => (string) Str::uuid()]]))->assertOk();

        $ownId = (string) Str::uuid();
        $this->xapi('PUT', '/statements?statementId=' . $ownId, $this->statement(['context' => ['registration' => $mine]]), $session)->assertNoContent();

        // reads: the list is limited to the registration, even when the AU asks for another one
        $list = $this->xapi('GET', '/statements?registration=' . (string) Str::uuid(), null, $session)->assertOk()->json('statements');
        $this->assertCount(1, $list);
        $this->assertSame($ownId, $list[0]['id']);
        $this->xapi('GET', '/statements?statementId=' . $foreign, null, $session)->assertNotFound();
        $this->xapi('GET', '/statements?statementId=' . $ownId, null, $session)->assertOk();
    }

    public function test_documents_of_a_session_are_limited_to_its_registration_and_profiles_are_read_only(): void
    {
        [$params, $oneTime] = $this->launch();
        $session = $this->asSession($this->sessionToken($oneTime));
        $state = [
            'activityId' => $params['activityId'],
            'agent' => json_encode($params['actor']),
            'stateId' => 'session',
        ];

        $own = '/activities/state?' . http_build_query($state + ['registration' => $params['registration']]);
        $this->xapi('PUT', $own, '{"a":1}', $session)->assertNoContent();
        $this->xapi('GET', $own, null, $session)->assertOk();

        $this->xapi('PUT', '/activities/state?' . http_build_query($state), '{"a":1}', $session)->assertForbidden();
        $this->xapi('PUT', '/activities/state?' . http_build_query($state + ['registration' => (string) Str::uuid()]), '{"a":1}', $session)->assertForbidden();
        $this->xapi('GET', '/activities/state?' . http_build_query($state + ['registration' => (string) Str::uuid()]), null, $session)->assertForbidden();

        $profile = '/activities/profile?' . http_build_query(['activityId' => $params['activityId'], 'profileId' => 'p']);
        $this->xapi('PUT', $profile, '{"a":1}', $session)->assertForbidden();
        $this->xapi('DELETE', $profile, null, $session)->assertForbidden();
        $agent = '/agents/profile?' . http_build_query(['agent' => json_encode($params['actor']), 'profileId' => 'cmi5LearnerPreferences']);
        $this->xapi('POST', $agent, '{"a":1}', $session)->assertForbidden();
    }

    public function test_completed_and_passed_statements_report_the_completion_of_the_au(): void
    {
        Event::fake([AuCompletionReported::class]);
        [$params, $oneTime] = $this->launch(9);
        $session = $this->asSession($this->sessionToken($oneTime));
        $context = ['registration' => $params['registration']];

        $this->xapi('POST', '/statements', $this->statement(['context' => $context]), $session)->assertOk();
        Event::assertNotDispatched(AuCompletionReported::class);

        $this->xapi('POST', '/statements', $this->statement(['context' => $context, 'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/passed']]), $session)->assertOk();
        Event::assertDispatched(AuCompletionReported::class, fn ($e) => $e->userId === $this->learner->getKey() && $e->auId === 9 && $e->registration === $params['registration']);
    }

    public function test_the_session_token_also_works_as_a_bearer_credential_for_the_lrs_only(): void
    {
        [$params, $oneTime] = $this->launch();
        $token = $this->sessionToken($oneTime);

        $this->xapi('POST', '/statements', $this->statement(['context' => ['registration' => $params['registration']]]), ['Authorization' => 'Bearer ' . $token])->assertOk();
    }
}
