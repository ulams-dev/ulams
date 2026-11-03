<?php

namespace Ulams\Identity\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\Auth\Models\LoginHandoffState;
use Ulams\Auth\Models\User;
use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Models\IdentityLink;
use Ulams\Identity\Tests\Support\FakeIdp;
use Ulams\Identity\Tests\Support\RunsOidcLogin;
use Ulams\Identity\Tests\TestCase;

/** OpenID Connect sign-in: authorization code with PKCE, a new student on first login, the one-time code. */
class OidcLoginTest extends TestCase
{
    use RunsOidcLogin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider();
    }

    public function testStartRedirectsToTheProviderWithPkceStateAndNonce(): void
    {
        $response = $this->start();

        $response->assertStatus(302)->assertHeader('Cache-Control', 'no-store, private');
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith(FakeIdp::ISSUER . '/protocol/openid-connect/auth?', $location);
        $q = $this->queryOf($location);
        $this->assertSame('code', $q['response_type']);
        $this->assertSame(FakeIdp::CLIENT_ID, $q['client_id']);
        $this->assertSame('openid email profile', $q['scope']);
        $this->assertSame('http://localhost/api/identity/oidc/acme/callback', $q['redirect_uri']);
        $this->assertSame('S256', $q['code_challenge_method']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $q['code_challenge']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $q['nonce']);
        $this->assertNotEmpty($q['state']);
        // the secret and the front end's challenge never travel to the provider
        $this->assertStringNotContainsString(FakeIdp::CLIENT_SECRET, $location);
        $this->assertStringNotContainsString($this->challenge(), $location);
        $this->assertSame(1, LoginHandoffState::query()->where('protocol', 'oidc')->count());
    }

    public function testTheCodeChallengeBelongsToAVerifierOnlyTheServerKnows(): void
    {
        $auth = $this->queryOf($this->start()->headers->get('Location'));
        $row = LoginHandoffState::query()->latest('id')->first();

        $this->assertSame(
            \Ulams\Identity\Support\Pkce::challenge($row->data['code_verifier']),
            $auth['code_challenge']
        );
        $this->assertSame($auth['nonce'], $row->data['nonce']);
    }

    public function testStartRefusesWhatItCannotTrust(): void
    {
        $this->start('acme', ['return_to' => 'https://evil.example/steal'])->assertStatus(422);
        $this->start('acme', ['return_to' => 'https://app.example.test.evil.example/'])->assertStatus(422);
        $this->start('acme', ['challenge' => 'short'])->assertStatus(422);
        $this->start('acme', ['challenge' => null])->assertStatus(422);
        $this->start('nope')->assertStatus(404);
        $this->assertSame(0, LoginHandoffState::query()->count());
    }

    public function testADisabledProviderCannotBeUsed(): void
    {
        $this->provider(['slug' => 'off', 'enabled' => false]);

        $this->start('off')->assertStatus(404);
    }

    public function testAProviderThatIsDownFailsTheStartBeforeAnyStateExists(): void
    {
        $this->idp->discovery = ['issuer' => 'https://evil.example/realms/acme'];

        $this->start()->assertStatus(502);
        $this->assertSame(0, LoginHandoffState::query()->count());
    }

    public function testFirstSignInCreatesAStudentAndTheBrowserGetsAOneTimeCodeNotAToken(): void
    {
        $this->newVerifier();
        $result = $this->signIn();

        $returned = $this->returned($result['response']);
        $this->assertSame('true', $returned['complete']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $returned['code']);
        $this->assertSame(['code', 'complete'], array_keys($returned));
        $result['response']->assertHeader('Referrer-Policy', 'no-referrer');

        $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('student'));
        $this->assertFalse($user->hasAnyRole(['admin', 'tutor']));
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue((bool) $user->is_active);
        $this->assertSame(['Ada', 'Lovelace'], [$user->first_name, $user->last_name]);
        $this->assertNull($user->password);
        $link = IdentityLink::query()->where('user_id', $user->getKey())->firstOrFail();
        $this->assertSame(['subject-1', 'jit'], [$link->subject, $link->linked_via]);

        $this->exchange($returned['code'])->assertOk()->assertJsonPath('success', true)->assertJsonStructure(['data' => ['token', 'expires_at']]);
        $this->assertTrue(IdentityAudit::query()->where('action', 'sso.login')->where('subject_id', (string) $user->getKey())->exists());
    }

    public function testTheAccessTokenWorks(): void
    {
        $this->newVerifier();
        $code = $this->returned($this->signIn()['response'])['code'];
        $token = $this->exchange($code)->json('data.token');

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('api/profile/me')->assertOk()->assertJsonPath('data.email', 'ada@example.com');
    }

    public function testTheTokenRequestCarriesTheVerifierTheRedirectUriAndTheSecretInTheHeader(): void
    {
        $this->newVerifier();
        $auth = $this->signIn()['auth'];

        $token = $this->idp->requestsTo('/token')[0];
        $this->assertSame('authorization_code', $token['form']['grant_type']);
        $this->assertSame('auth-code', $token['form']['code']);
        $this->assertSame('http://localhost/api/identity/oidc/acme/callback', $token['form']['redirect_uri']);
        $this->assertSame($auth['code_challenge'], \Ulams\Identity\Support\Pkce::challenge($token['form']['code_verifier']));
        $this->assertSame('Basic ' . base64_encode(FakeIdp::CLIENT_ID . ':' . FakeIdp::CLIENT_SECRET), $token['headers']['Authorization']);
        $this->assertArrayNotHasKey('client_secret', $token['form']);
    }

    public function testClientSecretPostSendsTheSecretInTheBodyInstead(): void
    {
        $this->provider(['slug' => 'post', 'oidc' => ['token_auth' => 'client_secret_post']]);

        $this->signIn([], ['slug' => 'post']);

        $token = $this->idp->requestsTo('/token')[0];
        $this->assertSame(FakeIdp::CLIENT_SECRET, $token['form']['client_secret']);
        $this->assertSame(FakeIdp::CLIENT_ID, $token['form']['client_id']);
        $this->assertArrayNotHasKey('Authorization', $token['headers']);
    }

    public function testASecondSignInUsesTheLinkEvenWhenTheEmailChanged(): void
    {
        $this->newVerifier();
        $this->signIn();
        $user = User::query()->where('email', 'ada@example.com')->firstOrFail();

        $this->newVerifier();
        $returned = $this->returned($this->signIn(['email' => 'someone.else@example.com'])['response']);

        $this->assertArrayHasKey('code', $returned);
        $this->assertSame(1, User::query()->where('email', 'like', '%@example.com')->count());
        $this->assertSame(1, IdentityLink::query()->count());
        $this->assertSame($user->getKey(), IdentityLink::query()->first()->user_id);
        $this->assertFalse(User::query()->where('email', 'someone.else@example.com')->exists());
    }

    public function testTheProviderCannotGrantRolesOrGroupsInThisMilestone(): void
    {
        $this->newVerifier();
        $this->signIn(['roles' => ['admin'], 'groups' => ['/admins'], 'role' => 'admin']);

        $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
        $this->assertSame(['student'], $user->getRoleNames()->all());
        $this->assertCount(0, $user->groups);
    }

    public function testTheDefaultRoleIsAppliedAndAdminIsNeverOffered(): void
    {
        $this->provider(['slug' => 'staff', 'default_role' => 'admin']);

        $this->signIn(['email' => 'boss@example.com', 'sub' => 'boss'], ['slug' => 'staff']);

        $this->assertSame(['student'], User::query()->where('email', 'boss@example.com')->firstOrFail()->getRoleNames()->all());
    }

    public function testUserinfoClaimsFillGapsAndItsSubjectMustMatch(): void
    {
        $this->provider(['slug' => 'ui', 'oidc' => ['userinfo' => true]]);
        $this->idp->userinfo = ['sub' => 'subject-1', 'given_name' => 'Wrong', 'family_name' => 'Name', 'phone' => '1'];

        $this->signIn(['given_name' => null, 'family_name' => null, 'name' => null], ['slug' => 'ui']);
        $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
        $this->assertSame(['Wrong', 'Name'], [$user->first_name, $user->last_name]);
        $this->assertSame('Bearer access-token-1', $this->idp->requestsTo('/userinfo')[0]['headers']['Authorization']);

        $this->idp->userinfo = ['sub' => 'someone-else'];
        $this->newVerifier();
        $response = $this->signIn(['sub' => 'subject-2', 'email' => 'bob@example.com'], ['slug' => 'ui'])['response'];
        $this->assertSame(['error' => 'provider_failed'], $this->queryOf((string) $response->headers->get('Location')));
        $this->assertSame('subject_mismatch', IdentityAudit::query()->where('action', 'sso.login_failed')->latest('id')->first()->data['reason']);
        $this->assertFalse(User::query()->where('email', 'bob@example.com')->exists());
    }

    public function testTheProviderDenyingTheLoginSendsTheBrowserBackWithOurOwnReason(): void
    {
        $started = $this->start();
        $state = $this->queryOf($started->headers->get('Location'))['state'];

        $response = $this->answerCallback('acme', $state, ['error' => 'access_denied<script>', 'code' => null]);

        $this->assertSame(['error' => 'provider_denied'], $this->queryOf((string) $response->headers->get('Location')));
        $this->assertStringStartsWith(self::RETURN_TO, (string) $response->headers->get('Location'));
        $this->assertSame(0, User::query()->where('email', 'ada@example.com')->count());
    }

    public function testASuccessClearsTheLastErrorOfTheProvider(): void
    {
        $this->signIn(['aud' => 'someone-else']);
        $this->assertSame('wrong_audience', \Ulams\Identity\Models\IdentityProvider::query()->first()->last_error);

        $this->newVerifier();
        $this->signIn();
        $provider = \Ulams\Identity\Models\IdentityProvider::query()->first();
        $this->assertNull($provider->last_error);
        $this->assertNotNull($provider->last_login_at);
    }

    public function testTheExchangeNeedsTheVerifierOfTheBrowserThatStartedTheLogin(): void
    {
        $this->newVerifier();
        $code = $this->returned($this->signIn()['response'])['code'];

        $this->exchange($code, str_repeat('x', 64))->assertStatus(400);
        // the code is burned by the failed try: a guessed verifier cannot be retried against it
        $this->exchange($code)->assertStatus(400);
    }

    public function testACodeWorksOnce(): void
    {
        $this->newVerifier();
        $code = $this->returned($this->signIn()['response'])['code'];

        $this->exchange($code)->assertOk();
        $this->exchange($code)->assertStatus(400);
    }

    public function testACodeExpiresAfterSixtySeconds(): void
    {
        $this->newVerifier();
        $code = $this->returned($this->signIn()['response'])['code'];

        $this->travel(61)->seconds();

        $this->exchange($code)->assertStatus(400);
    }

    public function testDeactivatingTheUserBetweenCallbackAndExchangeStopsTheLogin(): void
    {
        $this->newVerifier();
        $code = $this->returned($this->signIn()['response'])['code'];
        User::query()->where('email', 'ada@example.com')->update(['is_active' => false]);

        $this->exchange($code)->assertStatus(400);
    }

    public function testNoTokenAppearsInAnyUrlOrAuditRow(): void
    {
        $this->newVerifier();
        $result = $this->signIn();
        $location = (string) $result['response']->headers->get('Location');

        $this->assertStringNotContainsString('access-token-1', $location);
        $this->assertStringNotContainsString('eyJ', $location);
        $rows = json_encode(DB::table('identity_audit')->get());
        $this->assertStringNotContainsString('access-token-1', $rows);
        $this->assertStringNotContainsString(FakeIdp::CLIENT_SECRET, $rows);
        $this->assertStringNotContainsString('eyJ', $rows);
    }
}
