<?php

namespace Ulams\Auth\Tests\API;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Ulams\Auth\Models\LoginHandoffCode;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\LoginHandoffService;
use Ulams\Auth\Tests\TestCase;
use Ulams\Core\Support\EntryFronts;
use Ulams\Core\Tests\Concerns\ForeignTenantToken;
use Ulams\Core\Tests\CreatesUsers;

/**
 * Moving a signed-in learner between the hosts of one academy (ADR 0195): a one-time code bound to the user, the target
 * host and the verifier of the browser at the target; the target must be the academy's front or an active entry host,
 * on issue and on redemption; no token in any answer to the source host. Every case of the plan's hand-off list.
 */
class SessionHandoffApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers, ForeignTenantToken;

    private const MAIN = 'coffee.app.example.test';
    private const SALES = 'sales.coffee.example.test';

    /** @var list<string> origins of the active entry hosts, changed by the tests the way the platform changes the academy's copy */
    private array $entryOrigins = ['http://sales.coffee.example.test'];

    private string $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.frontend_url', 'http://' . self::MAIN);
        Config::set('ulams.core.security.admin_url', 'http://coffee.admin.example.test');
        Config::set('app.url', 'http://coffee.api.example.test');
        $this->verifier = Str::random(64);
        app(EntryFronts::class)->addSource(fn () => $this->entryOrigins);
    }

    private function challenge(?string $verifier = null): string
    {
        return LoginHandoffService::challengeFor($verifier ?? $this->verifier);
    }

    private function learner(array $attributes = []): array
    {
        $user = $this->makeStudent($attributes);

        // a login session, as the login endpoint and the exchanges create it
        return [$user, app(\Ulams\Auth\Services\Contracts\AuthServiceContract::class)->createTokenForUser($user)->accessToken];
    }

    private function bearer(string $token): array
    {
        // the guard remembers the user of the previous request of this test: each request authenticates from its own header
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer ' . $token];
    }

    private function issue(string $token, string $host = self::SALES, ?string $challenge = null)
    {
        return $this->postJson('/api/auth/handoff', ['host' => $host, 'challenge' => $challenge ?? $this->challenge()], $this->bearer($token));
    }

    private function exchange(string $code, ?string $verifier = null, string $host = self::SALES)
    {
        return $this->postJson('/api/auth/handoff/exchange', ['code' => $code, 'verifier' => $verifier ?? $this->verifier, 'host' => $host]);
    }

    private function code(string $token, string $host = self::SALES): string
    {
        return (string) $this->issue($token, $host)->assertOk()->json('data.code');
    }

    private function forgetEntryHosts(): void
    {
        app(EntryFronts::class)->forget();
    }

    public function testASignedInLearnerGetsACodeAndTheTargetHostRedeemsItForATokenOfItsOwn(): void
    {
        [$user, $token] = $this->learner(['email' => 'ann@coffee.test']);

        $issued = $this->issue($token)->assertOk()->assertJsonPath('data.expires_in', 60);
        $code = $issued->json('data.code');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $code);
        // the answer to the source host carries the code and never a token
        $this->assertStringNotContainsString($token, $issued->getContent());
        $this->assertArrayNotHasKey('token', $issued->json('data'));
        $issued->assertHeader('Cache-Control', 'no-store, private');

        $exchanged = $this->exchange($code)->assertOk();
        $new = $exchanged->json('data.token');
        $this->assertNotEmpty($new);
        $this->assertNotSame($token, $new, 'the target host gets a session of its own');
        $this->assertNotEmpty($exchanged->json('data.expires_at'));
        $this->getJson('/api/profile/me', $this->bearer($new))->assertOk()->assertJsonPath('data.email', 'ann@coffee.test');
        // the source session is untouched
        $this->getJson('/api/profile/me', $this->bearer($token))->assertOk();
    }

    public function testTheAcademysOwnFrontIsATargetToo(): void
    {
        [, $token] = $this->learner();

        $this->exchange($this->code($token, self::MAIN), null, self::MAIN)->assertOk();
        // a port is not part of the host
        $this->exchange($this->code($token, self::MAIN . ':4321'), null, strtoupper(self::MAIN) . ':4321')->assertOk();
    }

    public function testTheCodeIsStoredAsAHashBoundToUserHostAndChallenge(): void
    {
        [$user, $token] = $this->learner();
        $code = $this->code($token);

        $this->assertDatabaseMissing('login_handoff_codes', ['code_hash' => $code]);
        $row = LoginHandoffCode::query()->where('code_hash', hash('sha256', $code))->firstOrFail();
        $this->assertSame([LoginHandoffCode::KIND_HANDOFF, $user->getKey(), self::SALES, $this->challenge()], [$row->kind, $row->user_id, $row->host, $row->challenge]);
        $this->assertEqualsWithDelta(60, now()->diffInSeconds($row->expires_at, false), 2);
    }

    public function testACodeWorksOnce(): void
    {
        [, $token] = $this->learner();
        $code = $this->code($token);

        $this->exchange($code)->assertOk();
        $this->exchange($code)->assertStatus(400);
    }

    public function testAnExpiredCodeIsRefused(): void
    {
        [, $token] = $this->learner();
        $code = $this->code($token);

        Carbon::setTestNow(now()->addSeconds(61));
        try {
            $this->exchange($code)->assertStatus(400);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function testACodeIsUselessAtAnotherHost(): void
    {
        [, $token] = $this->learner();
        $this->entryOrigins[] = 'http://kubernetes.coffee.example.test';
        $this->forgetEntryHosts();
        $code = $this->code($token);

        $this->exchange($code, null, 'kubernetes.coffee.example.test')->assertStatus(400);
        // and it is spent: the right host cannot use it afterwards either
        $this->exchange($code)->assertStatus(400);
    }

    public function testACodeNeedsTheVerifierOfTheBrowserThatAskedForIt(): void
    {
        [, $token] = $this->learner();
        $code = $this->code($token);

        $this->exchange($code, Str::random(64))->assertStatus(400);
        // guessing verifiers against a stolen code is not possible: the first miss burned it
        $this->exchange($code)->assertStatus(400);
    }

    public function testMissingOrMalformedPiecesAreRefusedBeforeAnythingIsLookedUp(): void
    {
        [, $token] = $this->learner();
        $code = $this->code($token);

        $this->postJson('/api/auth/handoff/exchange', ['code' => $code, 'host' => self::SALES])->assertStatus(422)->assertJsonValidationErrors('verifier');
        $this->postJson('/api/auth/handoff/exchange', ['verifier' => $this->verifier, 'host' => self::SALES])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->postJson('/api/auth/handoff/exchange', ['code' => $code, 'verifier' => $this->verifier])->assertStatus(422)->assertJsonValidationErrors('host');
        $this->postJson('/api/auth/handoff/exchange', ['code' => $code, 'verifier' => 'short', 'host' => self::SALES])->assertStatus(422);
        // none of that spent the code
        $this->exchange($code)->assertOk();

        $this->postJson('/api/auth/handoff', ['challenge' => $this->challenge()], $this->bearer($token))->assertStatus(422)->assertJsonValidationErrors('host');
        $this->postJson('/api/auth/handoff', ['host' => self::SALES], $this->bearer($token))->assertStatus(422)->assertJsonValidationErrors('challenge');
        $this->postJson('/api/auth/handoff', ['host' => self::SALES, 'challenge' => 'not-a-challenge'], $this->bearer($token))->assertStatus(422);
    }

    public function testOnlyAFrontOfThisAcademyIsATarget(): void
    {
        [, $token] = $this->learner();
        $before = LoginHandoffCode::query()->count();

        foreach ([
            'coffee.api.example.test', 'coffee.admin.example.test', 'evil.example.test', 'sales.coffee.example.test.evil.test',
            'tea.example.test', 'localhost', '127.0.0.1', 'coffee.app.example.test@evil.test', 'coffee.app.example.test/evil', '',
        ] as $host) {
            $this->issue($token, $host)->assertStatus($host === '' ? 422 : 403);
        }

        $this->assertSame($before, LoginHandoffCode::query()->count(), 'no code was issued');
    }

    public function testADisabledEntryPointGetsNoCodeAndRedeemsNoCode(): void
    {
        [, $token] = $this->learner();
        $code = $this->code($token);

        // the platform disables the entry point after the code was issued
        $this->entryOrigins = [];
        $this->forgetEntryHosts();

        $this->exchange($code)->assertStatus(400);
        $this->issue($token)->assertStatus(403);
        $this->entryOrigins = ['http://sales.coffee.example.test'];
        $this->forgetEntryHosts();
        // the spent code stays spent
        $this->exchange($code)->assertStatus(400);
    }

    public function testNeedsALoginAndNeverAScopedToken(): void
    {
        $this->postJson('/api/auth/handoff', ['host' => self::SALES, 'challenge' => $this->challenge()])->assertUnauthorized();

        // a scoped (agent) token must not mint a login for another host
        $user = $this->makeStudent();
        $issued = app(\Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract::class)->issue($user, 'agent', ['learner:write'], 30, 'agent', 'tester', 'admin', null);
        $this->postJson('/api/auth/handoff', ['host' => self::SALES, 'challenge' => $this->challenge()], $this->bearer($issued->secret))->assertForbidden();
    }

    public function testAnotherAcademysTokenAndCodeAreNothingHere(): void
    {
        [, $token] = $this->learner();
        $this->switchToTenantWithOwnKeys();

        // the other academy has its own keys: the token is not a login there
        $this->sendAs($token, 'POST', '/api/auth/handoff', ['host' => self::SALES, 'challenge' => $this->challenge()])->assertUnauthorized();
        // and a code of the first academy is unknown here (its row is in that academy's database)
        $unknown = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->exchange($unknown)->assertStatus(400);
    }

    public function testADeactivatedOrUnverifiedUserGetsNothing(): void
    {
        [$user, $token] = $this->learner();
        $code = $this->code($token);
        $user->forceFill(['is_active' => false])->save();

        $this->exchange($code)->assertStatus(400);

        $unverified = User::factory()->create(['email_verified_at' => null]);
        $unverifiedToken = app(\Ulams\Auth\Services\Contracts\AuthServiceContract::class)->createTokenForUser($unverified)->accessToken;
        $other = $this->code($unverifiedToken);
        $this->exchange($other)->assertStatus(400);
    }

    public function testCodesOfOtherLoginsAreNotHandOffCodesAndHandOffCodesAreNotLoginCodes(): void
    {
        [$user, $token] = $this->learner();
        $service = app(LoginHandoffService::class);

        // a social login code (kind login, bound to a challenge) cannot be redeemed at the hand-off exchange
        $state = $service->startState('social', 'google', 'coffee.api.example.test', 'http://' . self::SALES . '/cb', $this->challenge());
        $row = $service->consumeState($state, 'social', 'google', 'coffee.api.example.test');
        $row->forceFill(['host' => self::SALES])->save();
        $login = $service->issueCode($row, LoginHandoffCode::KIND_LOGIN, (int) $user->getKey());
        $this->exchange($login)->assertStatus(400);

        // and a hand-off code cannot be redeemed at the social exchange, whatever host it names
        $code = $this->code($token);
        LoginHandoffCode::query()->where('code_hash', hash('sha256', $code))->update(['host' => 'coffee.api.example.test']);
        $this->postJson('/api/auth/social/exchange', ['code' => $code, 'verifier' => $this->verifier])->assertStatus(400);
    }

    public function testEveryRefusalLooksTheSameToTheBrowser(): void
    {
        [, $token] = $this->learner();
        $good = $this->code($token);
        $this->exchange($good)->assertOk();

        $answers = [
            $this->exchange($good)->getContent(),
            $this->exchange(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='))->getContent(),
            $this->exchange($this->code($token), Str::random(64))->getContent(),
        ];

        $this->assertCount(1, array_unique($answers));
    }

    public function testExchangeIsThrottledPerAddress(): void
    {
        $unknown = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        foreach (range(1, 30) as $_) {
            $this->exchange($unknown)->assertStatus(400);
        }

        $this->exchange($unknown)->assertStatus(429);
    }

    public function testSignOutEverywhereRevokesEveryLoginSessionOfTheUserAndNoScopedToken(): void
    {
        [$user, $first] = $this->learner();
        $second = $this->exchange($this->code($first))->assertOk()->json('data.token');
        $scoped = app(\Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract::class)->issue($user, 'ci', ['learner:read'], 30, 'ci', 'pipeline', 'admin', null)->secret;
        [, $someoneElse] = $this->learner();

        $this->postJson('/api/auth/logout', ['everywhere' => true], $this->bearer($second))->assertOk();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/profile/me', $this->bearer($first))->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/profile/me', $this->bearer($second))->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/profile/me', $this->bearer($scoped))->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/profile/me', $this->bearer($someoneElse))->assertOk();
    }

    public function testPlainLogoutOnlyEndsThisSession(): void
    {
        [, $first] = $this->learner();
        $second = $this->exchange($this->code($first))->assertOk()->json('data.token');

        $this->postJson('/api/auth/logout', [], $this->bearer($second))->assertOk();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/profile/me', $this->bearer($second))->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/profile/me', $this->bearer($first))->assertOk();
    }
}
