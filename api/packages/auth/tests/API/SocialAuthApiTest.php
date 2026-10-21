<?php

namespace Ulams\Auth\Tests\API;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Mockery\MockInterface;
use Ulams\Auth\Enums\SocialiteProvidersEnum;
use Ulams\Auth\Events\AccountRegistered;
use Ulams\Auth\Models\LoginHandoffCode;
use Ulams\Auth\Models\LoginHandoffState;
use Ulams\Auth\Models\PreUser;
use Ulams\Auth\Models\SocialAccount;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\LoginHandoffService;
use Ulams\Auth\Tests\TestCase;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Core\Tests\CreatesUsers;

/**
 * Social login (ADR 0121): allow-listed return URL, signed single-use state, one-time code instead
 * of a token in the URL, strict linking by e-mail.
 */
class SocialAuthApiTest extends TestCase
{
    use DatabaseTransactions, WithFaker, CreatesUsers;

    private const FRONT = 'https://app.example.test';
    private const RETURN_URL = 'https://app.example.test/auth/callback';

    private string $provider;
    private MockInterface $socialite;
    private MockInterface $socialUser;
    private ?string $capturedState = null;
    private string $verifier;
    private bool $providerFails = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = SocialiteProvidersEnum::GOOGLE;
        $this->verifier = Str::random(64);
        Config::set('app.frontend_url', self::FRONT);
        Config::set(UlamsAuthServiceProvider::CONFIG_KEY . '.socialite_remember_me', false);

        $this->socialite = Mockery::mock('Laravel\Socialite\SocialiteManager');
        $this->socialite->shouldReceive('stateless')->andReturn($this->socialite);
        $this->socialite->shouldReceive('with')->andReturnUsing(function (array $parameters) {
            $this->capturedState = $parameters['state'] ?? null;

            return $this->socialite;
        });
        Socialite::shouldReceive('driver')->with($this->provider)->andReturn($this->socialite);
        $this->socialUser = Mockery::mock('Laravel\Socialite\Two\User');
        $this->socialite->shouldReceive('user')->andReturnUsing(function () {
            if ($this->providerFails) {
                throw new \RuntimeException('invalid_grant');
            }

            return $this->socialUser;
        });
    }

    // ---- helpers -----------------------------------------------------------------------------

    private function challenge(): string
    {
        return LoginHandoffService::challengeFor($this->verifier);
    }

    /** Starts a login and returns the state the provider would receive back. */
    private function startLogin(?string $returnUrl = self::RETURN_URL, array $query = []): string
    {
        $this->socialite->shouldReceive('redirect')->andReturn(new RedirectResponse('https://accounts.example.test/auth'));
        $this->capturedState = null;

        $params = array_filter(['challenge' => $this->challenge(), 'return_url' => $returnUrl], fn ($v) => $v !== null);
        $this->get('api/auth/social/' . $this->provider . '?' . http_build_query($query + $params))
            ->assertRedirect('https://accounts.example.test/auth');
        $this->assertNotNull($this->capturedState);

        return $this->capturedState;
    }

    private function socialUserIs(string $id, ?string $email, bool|string|null $verified = true, string $name = 'Jane Doe'): void
    {
        $this->socialUser = Mockery::mock('Laravel\Socialite\Two\User');
        $this->socialUser->shouldReceive('getId')->andReturn($id);
        $this->socialUser->shouldReceive('getEmail')->andReturn($email);
        $this->socialUser->shouldReceive('getName')->andReturn($name);
        $this->socialUser->user = $verified === null ? [] : ['email_verified' => $verified];
    }

    private function callCallback(string $state, ?string $provider = null, array $query = [])
    {
        return $this->get('api/auth/social/' . ($provider ?? $this->provider) . '/callback?' . http_build_query(['state' => $state] + $query));
    }

    /** @return array<string, string> query of the redirect target */
    private function redirectQuery($response): array
    {
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(self::RETURN_URL, $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return $query;
    }

    private function exchange(string $code, ?string $verifier = null)
    {
        return $this->postJson('api/auth/social/exchange', ['code' => $code, 'verifier' => $verifier ?? $this->verifier]);
    }

    private function loginUrlCode(string $state, string $email, string $id = '1001', bool|string|null $verified = true): string
    {
        $this->socialUserIs($id, $email, $verified);
        $query = $this->redirectQuery($this->callCallback($state));
        $this->assertArrayHasKey('code', $query, json_encode($query));

        return $query['code'];
    }

    // ---- start of the login ----------------------------------------------------------------------

    public function testShouldReturnValidationErrorIllegalProvider(): void
    {
        $this->getJson('api/auth/social/test?challenge=' . $this->challenge())
            ->assertJsonValidationErrorFor('provider');
    }

    public function testShouldRequireAChallenge(): void
    {
        $this->getJson('api/auth/social/' . $this->provider)->assertJsonValidationErrorFor('challenge');
        $this->getJson('api/auth/social/' . $this->provider . '?challenge=short')->assertJsonValidationErrorFor('challenge');
    }

    public function testShouldRedirectToProviderWithASignedStateAndKeepTheReturnUrlServerSide(): void
    {
        $state = $this->startLogin();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}\.\d{10}\.[A-Za-z0-9_-]{43}$/', $state);
        $row = LoginHandoffState::query()->firstOrFail();
        $this->assertSame(self::RETURN_URL, $row->return_to);
        $this->assertSame($this->challenge(), $row->challenge);
        $this->assertSame('google', $row->provider);
        $this->assertNull($row->consumed_at);
        // only the hash of the nonce is stored
        $this->assertNotSame(Str::before($state, '.'), $row->getRawOriginal('state_hash'));
    }

    public function testShouldFallBackToTheTenantFrontEndWithoutReturnUrl(): void
    {
        $this->startLogin(null);

        $this->assertSame(self::FRONT . '/authentication', LoginHandoffState::query()->firstOrFail()->return_to);
    }

    public function testShouldRefuseReturnUrlsOutsideTheAllowList(): void
    {
        foreach ([
            'https://evil.example/steal',
            'https://app.example.test.evil.example/x',
            'https://evil.example/https://app.example.test/',
            'https://app.example.test@evil.example/',
            'https://evil.example\\@app.example.test/',
            'https://app.example.test:8443/x',
            'http://app.example.test/x',
            '//evil.example/x',
            '/relative/path',
            'javascript:alert(1)',
            "https://app.example.test/\r\nLocation: https://evil.example",
            'https://app.example.test/ x',
        ] as $url) {
            $this->getJson('api/auth/social/google?' . http_build_query(['challenge' => $this->challenge(), 'return_url' => $url]))
                ->assertStatus(422);
        }

        $this->assertSame(0, LoginHandoffState::query()->count());
    }

    public function testShouldAcceptTheTenantFrontAdminAndConfiguredOrigins(): void
    {
        Config::set('ulams.core.security.admin_url', 'https://admin.example.test');
        Config::set('ulams.core.security.trusted_origins', ['https://partner.example.test']);

        foreach (['https://admin.example.test/login', 'https://partner.example.test/', self::FRONT . '/a?b=c#/d'] as $url) {
            $this->socialite->shouldReceive('redirect')->andReturn(new RedirectResponse('https://accounts.example.test/auth'));
            $this->get('api/auth/social/google?' . http_build_query(['challenge' => $this->challenge(), 'return_url' => $url]))
                ->assertRedirect('https://accounts.example.test/auth');
        }
    }

    public function testContentOriginsAreNeverAllowed(): void
    {
        $this->getJson('api/auth/social/google?' . http_build_query(['challenge' => $this->challenge(), 'return_url' => 'https://acme.content.example.test/']))
            ->assertStatus(422);
    }

    // ---- callback: state --------------------------------------------------------------------------

    public function testCallbackWithoutStateIsRefused(): void
    {
        $this->getJson('api/auth/social/google/callback')->assertStatus(400);
        $this->getJson('api/auth/social/google/callback?state=')->assertStatus(400);
    }

    public function testLegacyUnsignedStateIsRefused(): void
    {
        $legacy = base64_encode(json_encode(['return_url' => 'https://evil.example']));

        $this->getJson('api/auth/social/google/callback?state=' . urlencode($legacy))->assertStatus(400);
    }

    public function testTamperedStateIsRefused(): void
    {
        $state = $this->startLogin();
        [$nonce, $exp, $sig] = explode('.', $state);

        $this->assertSame(400, $this->callCallback($nonce . '.' . $exp . '.' . strrev($sig))->getStatusCode());
        $this->assertSame(400, $this->callCallback($nonce . '.' . ($exp + 3600) . '.' . $sig)->getStatusCode());
        $this->assertSame(400, $this->callCallback(Str::random(43) . '.' . $exp . '.' . $sig)->getStatusCode());
        $this->assertSame(400, $this->callCallback($nonce . '.' . $exp)->getStatusCode());
        // signed for another key
        Config::set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $this->assertSame(400, $this->callCallback($state)->getStatusCode());
    }

    public function testExpiredStateIsRefused(): void
    {
        $state = $this->startLogin();
        $this->socialUserIs('1', 'someone@gmail.com');

        $this->travel(11)->minutes();

        $this->callCallback($state)->assertStatus(400);
        $this->assertSame(0, LoginHandoffCode::query()->count());
    }

    public function testStateWorksOnlyOnce(): void
    {
        $user = User::factory()->create(['email' => 'first@gmail.com']);
        SocialAccount::factory(['user_id' => $user->getKey(), 'provider' => 'google', 'provider_id' => '77'])->create();
        $state = $this->startLogin();
        $this->socialUserIs('77', null, null);

        $this->assertArrayHasKey('code', $this->redirectQuery($this->callCallback($state)));
        $this->callCallback($state)->assertStatus(400);
        $this->assertSame(1, LoginHandoffCode::query()->count());
    }

    public function testStateStartedForAnotherProviderIsRefused(): void
    {
        $state = $this->startLogin();

        $this->callCallback($state, 'facebook')->assertStatus(400);
        $this->assertNull(LoginHandoffState::query()->firstOrFail()->consumed_at);
    }

    public function testStateOfAnotherTenantHostIsRefused(): void
    {
        $state = $this->startLogin();
        $this->socialUserIs('1', 'someone@gmail.com');

        $this->call('GET', 'http://other-tenant.localhost/api/auth/social/google/callback', ['state' => $state])->assertStatus(400);
        $this->assertSame(0, LoginHandoffCode::query()->count());
    }

    public function testProviderErrorSendsTheBrowserBackWithAReasonOnly(): void
    {
        $state = $this->startLogin();

        $query = $this->redirectQuery($this->callCallback($state, null, ['error' => 'access_denied']));

        $this->assertSame(['error' => 'provider_denied'], $query);
    }

    public function testProviderFailureSendsTheBrowserBackWithAReason(): void
    {
        $state = $this->startLogin();
        $this->providerFails = true;

        $this->assertSame(['error' => 'provider_failed'], $this->redirectQuery($this->callCallback($state)));
    }

    // ---- callback: code and exchange --------------------------------------------------------------

    public function testRedirectCarriesACodeAndNeverAToken(): void
    {
        $user = User::factory()->create(['email' => 'someone@gmail.com']);
        SocialAccount::factory(['user_id' => $user->getKey(), 'provider' => 'google', 'provider_id' => '5'])->create();
        $state = $this->startLogin();
        $this->socialUserIs('5', 'someone@gmail.com');

        $response = $this->callCallback($state);
        $query = $this->redirectQuery($response);

        $this->assertSame(['code', 'complete'], array_keys($query));
        $this->assertSame('true', $query['complete']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $query['code']);
        $this->assertStringNotContainsString('token', $response->headers->get('Location'));
        $this->assertSame(0, $user->tokens()->count(), 'no access token exists before the exchange');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        // the code is stored hashed
        $this->assertDatabaseMissing('login_handoff_codes', ['code_hash' => $query['code']]);
        $this->assertDatabaseHas('login_handoff_codes', ['code_hash' => hash('sha256', $query['code']), 'user_id' => $user->getKey()]);
    }

    public function testHashRoutersKeepTheirFragment(): void
    {
        $user = User::factory()->create();
        SocialAccount::factory(['user_id' => $user->getKey(), 'provider' => 'google', 'provider_id' => '6'])->create();
        $state = $this->startLogin(self::FRONT . '/#/authentication');
        $this->socialUserIs('6', null, null);

        $location = $this->callCallback($state)->assertRedirect()->headers->get('Location');

        $this->assertMatchesRegularExpression('#^' . preg_quote(self::FRONT, '#') . '/\?code=[A-Za-z0-9_-]{43}&complete=true\#/authentication$#', $location);
    }

    public function testExchangeReturnsATokenThatWorks(): void
    {
        $user = $this->makeStudent(['email' => 'someone@gmail.com']);
        SocialAccount::factory(['user_id' => $user->getKey(), 'provider' => 'google', 'provider_id' => '5'])->create();
        $code = $this->loginUrlCode($this->startLogin(), 'someone@gmail.com', '5');

        $response = $this->exchange($code)->assertOk()->assertJsonPath('data.complete', true);
        $token = $response->json('data.token');

        $this->assertNotEmpty($token);
        $this->assertNotEmpty($response->json('data.expires_at'));
        $this->getJson('api/profile/me', ['Authorization' => 'Bearer ' . $token])->assertOk()
            ->assertJsonPath('data.email', 'someone@gmail.com');
    }

    public function testExchangeHonoursRememberMe(): void
    {
        Config::set(UlamsAuthServiceProvider::CONFIG_KEY . '.socialite_remember_me', true);
        $user = User::factory()->create();
        SocialAccount::factory(['user_id' => $user->getKey(), 'provider' => 'google', 'provider_id' => '8'])->create();
        $code = $this->loginUrlCode($this->startLogin(), 'x@gmail.com', '8', null);

        $this->exchange($code)->assertOk();

        $token = $user->tokens()->first();
        $this->assertEquals((new Carbon($token->created_at))->addMonth()->format('Y-m-d'), (new Carbon($token->expires_at))->format('Y-m-d'));
    }

    public function testCodeWorksOnlyOnce(): void
    {
        $user = User::factory()->create();
        SocialAccount::factory(['user_id' => $user->getKey(), 'provider' => 'google', 'provider_id' => '9'])->create();
        $code = $this->loginUrlCode($this->startLogin(), 'x@gmail.com', '9', null);

        $this->exchange($code)->assertOk();
        $this->exchange($code)->assertStatus(400);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function testCodeWithoutOrWithTheWrongVerifierIsUselessAndBurned(): void
    {
        $user = User::factory()->create();
        SocialAccount::factory(['user_id' => $user->getKey(), 'provider' => 'google', 'provider_id' => '9'])->create();
        $code = $this->loginUrlCode($this->startLogin(), 'x@gmail.com', '9', null);

        $this->postJson('api/auth/social/exchange', ['code' => $code])->assertStatus(422);
        $this->exchange($code, Str::random(64))->assertStatus(400);
        // the attacker's failed guess burned the code: even the real verifier cannot redeem it now
        $this->exchange($code)->assertStatus(400);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function testCodeExpiresAfterSixtySeconds(): void
    {
        $user = User::factory()->create();
        SocialAccount::factory(['user_id' => $user->getKey(), 'provider' => 'google', 'provider_id' => '9'])->create();
        $code = $this->loginUrlCode($this->startLogin(), 'x@gmail.com', '9', null);

        $this->travel(61)->seconds();

        $this->exchange($code)->assertStatus(400);
    }

    public function testCodeCannotBeRedeemedOnAnotherTenantHost(): void
    {
        $user = User::factory()->create();
        SocialAccount::factory(['user_id' => $user->getKey(), 'provider' => 'google', 'provider_id' => '9'])->create();
        $code = $this->loginUrlCode($this->startLogin(), 'x@gmail.com', '9', null);

        $this->postJson('http://other-tenant.localhost/api/auth/social/exchange', ['code' => $code, 'verifier' => $this->verifier])->assertStatus(400);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function testMalformedAndUnknownCodesAreRefused(): void
    {
        $this->exchange('nope')->assertStatus(400);
        $this->exchange(Str::random(43))->assertStatus(400);
        $this->postJson('api/auth/social/exchange', ['code' => Str::random(43), 'verifier' => 'short'])->assertStatus(422);
        $this->postJson('api/auth/social/exchange', [])->assertStatus(422);
    }

    public function testUserDeactivatedBetweenCallbackAndExchangeGetsNoToken(): void
    {
        $user = User::factory()->create();
        SocialAccount::factory(['user_id' => $user->getKey(), 'provider' => 'google', 'provider_id' => '9'])->create();
        $code = $this->loginUrlCode($this->startLogin(), 'x@gmail.com', '9', null);

        $user->update(['is_active' => false]);

        $this->exchange($code)->assertStatus(400);
        $this->assertSame(0, $user->tokens()->count());
    }

    // ---- who may sign in --------------------------------------------------------------------------

    public function testInactiveOrUnconfirmedLinkedUsersGetAReasonAndNoCode(): void
    {
        $inactive = User::factory()->create(['is_active' => false]);
        SocialAccount::factory(['user_id' => $inactive->getKey(), 'provider' => 'google', 'provider_id' => '31'])->create();
        $this->socialUserIs('31', null, null);
        $this->assertSame(['error' => 'account_unavailable'], $this->redirectQuery($this->callCallback($this->startLogin())));

        $unconfirmed = User::factory()->create(['email_verified_at' => null]);
        SocialAccount::factory(['user_id' => $unconfirmed->getKey(), 'provider' => 'google', 'provider_id' => '32'])->create();
        $this->socialUserIs('32', null, null);
        Event::fake([AccountRegistered::class]);

        $this->assertSame(['error' => 'email_unverified'], $this->redirectQuery($this->callCallback($this->startLogin())));
        $this->assertSame(0, LoginHandoffCode::query()->count());
    }

    // ---- linking by e-mail --------------------------------------------------------------------------

    public function testLinksAnExistingAccountWhenGoogleVouchesForATrustedDomain(): void
    {
        $user = User::factory()->create(['email' => 'owner@gmail.com']);

        $code = $this->loginUrlCode($this->startLogin(), 'Owner@Gmail.com', '41', true);

        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->getKey(), 'provider' => 'google', 'provider_id' => '41']);
        $this->exchange($code)->assertOk();
    }

    public function testStringTrueFromTheProviderCountsAsVerified(): void
    {
        $user = User::factory()->create(['email' => 'owner@gmail.com']);

        $this->loginUrlCode($this->startLogin(), 'owner@gmail.com', '41', 'true');

        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->getKey()]);
    }

    public function testDoesNotLinkWhenTheProviderDoesNotVouchForTheAddress(): void
    {
        $user = User::factory()->create(['email' => 'owner@gmail.com']);

        foreach ([false, 'false', null] as $i => $verified) {
            $this->socialUserIs('4' . $i, 'owner@gmail.com', $verified);
            $this->assertSame(['error' => 'link_required'], $this->redirectQuery($this->callCallback($this->startLogin())));
        }

        $this->assertDatabaseMissing('social_accounts', ['user_id' => $user->getKey()]);
        $this->assertSame(0, LoginHandoffCode::query()->count());
    }

    public function testDoesNotLinkForDomainsTheProviderIsNotAuthoritativeFor(): void
    {
        $user = User::factory()->create(['email' => 'owner@acme.example']);
        $this->socialUserIs('42', 'owner@acme.example', true);

        $this->assertSame(['error' => 'link_required'], $this->redirectQuery($this->callCallback($this->startLogin())));
        $this->assertDatabaseMissing('social_accounts', ['user_id' => $user->getKey()]);

        // an operator may list the organisation's own Google Workspace domain
        Config::set(UlamsAuthServiceProvider::CONFIG_KEY . '.social_login.trusted_domains.google', ['gmail.com', 'acme.example']);
        $this->assertArrayHasKey('code', $this->redirectQuery($this->callCallback($this->startLogin())));
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->getKey()]);
    }

    public function testNeverLinksAdminsTutorsOrSuperadminsByEmail(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin@gmail.com']);
        $tutor = $this->makeInstructor(['email' => 'tutor@gmail.com']);
        $super = User::factory()->create(['email' => 'boss@gmail.com']);
        Config::set(UlamsAuthServiceProvider::CONFIG_KEY . '.superadmins', ['boss@gmail.com']);

        foreach ([[$admin, '51'], [$tutor, '52'], [$super, '53']] as [$user, $id]) {
            $this->socialUserIs($id, $user->email, true);
            $this->assertSame(['error' => 'link_required'], $this->redirectQuery($this->callCallback($this->startLogin())), $user->email);
            $this->assertDatabaseMissing('social_accounts', ['user_id' => $user->getKey()]);
        }
    }

    public function testFacebookNeverLinksByEmail(): void
    {
        $facebook = Mockery::mock('Laravel\Socialite\SocialiteManager');
        $facebook->shouldReceive('stateless')->andReturn($facebook);
        $facebook->shouldReceive('with')->andReturnUsing(function (array $p) use ($facebook) {
            $this->capturedState = $p['state'];

            return $facebook;
        });
        $facebook->shouldReceive('redirect')->andReturn(new RedirectResponse('https://facebook.example.test/auth'));
        Socialite::shouldReceive('driver')->with('facebook')->andReturn($facebook);
        $user = User::factory()->create(['email' => 'owner@gmail.com']);

        $this->get('api/auth/social/facebook?' . http_build_query(['challenge' => $this->challenge(), 'return_url' => self::RETURN_URL]))->assertRedirect();
        $this->socialUserIs('61', 'owner@gmail.com', true);
        $facebook->shouldReceive('user')->andReturnUsing(fn () => $this->socialUser);

        $this->assertSame(['error' => 'link_required'], $this->redirectQuery($this->callCallback($this->capturedState, 'facebook')));
        $this->assertDatabaseMissing('social_accounts', ['user_id' => $user->getKey()]);
    }

    public function testClaimingAnUnconfirmedAccountDropsTheSquattersPasswordAndTokens(): void
    {
        $squatted = User::factory()->create(['email' => 'victim@gmail.com', 'email_verified_at' => null]);
        $squatterToken = $squatted->createToken('squatter')->accessToken;

        $code = $this->loginUrlCode($this->startLogin(), 'victim@gmail.com', '71', true);

        $squatted->refresh();
        $this->assertTrue($squatted->hasVerifiedEmail());
        $this->assertNull($squatted->password);
        $this->postJson('api/auth/login', ['email' => 'victim@gmail.com', 'password' => 'secret'])->assertStatus(422);
        $this->assertNotEmpty($squatterToken);
        $this->assertSame(0, $squatted->tokens()->where('revoked', false)->count(), 'the squatter is signed out');
        $this->exchange($code)->assertOk();
    }

    public function testLinkingNeverMarksAnUnverifiedAddressVerified(): void
    {
        $squatted = User::factory()->create(['email' => 'victim@gmail.com', 'email_verified_at' => null]);

        $this->socialUserIs('72', 'victim@gmail.com', false);
        $this->redirectQuery($this->callCallback($this->startLogin()));

        $this->assertFalse($squatted->refresh()->hasVerifiedEmail());
    }

    // ---- new accounts ---------------------------------------------------------------------------------

    public function testCreatesAnAccountWithoutAPasswordWhenGoogleVouchesForTheAddress(): void
    {
        $email = $this->faker->unique()->safeEmail;

        $code = $this->loginUrlCode($this->startLogin(), $email, '81', true);

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNull($user->password);
        $this->assertSame('Jane', $user->first_name);
        $this->assertSame('Doe', $user->last_name);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->getKey(), 'provider' => 'google', 'provider_id' => '81']);
        // the old code stored the literal password "1" for social accounts
        foreach (['1', 'secret', ''] as $password) {
            $this->postJson('api/auth/login', ['email' => $email, 'password' => $password])->assertStatus(422);
        }
        $this->exchange($code)->assertOk();
    }

    public function testCreatesAnUnconfirmedAccountAndMailsAConfirmationWhenTheProviderDoesNotVouch(): void
    {
        Event::fake([AccountRegistered::class]);
        $email = $this->faker->unique()->safeEmail;
        $this->socialUserIs('82', $email, null);

        $query = $this->redirectQuery($this->callCallback($this->startLogin()));

        $this->assertSame(['error' => 'email_unverified'], $query);
        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        Event::assertDispatched(AccountRegistered::class);
        $this->assertSame(0, LoginHandoffCode::query()->count());
    }

    public function testPreUserFlowUsesTheSameCodeExchange(): void
    {
        $this->socialUserIs('83', null, null);

        $query = $this->redirectQuery($this->callCallback($this->startLogin()));

        $this->assertSame('false', $query['complete']);
        $this->assertArrayNotHasKey('token', $query);
        $pre = PreUser::query()->firstOrFail();
        $this->assertStringNotContainsString($pre->token, http_build_query($query));

        $response = $this->exchange($query['code'])->assertOk()->assertJsonPath('data.complete', false);
        $this->assertSame($pre->token, $response->json('data.pre_user_token'));
        $this->assertNull($response->json('data.token'));
    }

    public function testShouldCreateUserFromPreUserAsUnconfirmed(): void
    {
        Event::fake([AccountRegistered::class]);
        $preUser = PreUser::factory()->create();
        $email = $this->faker->unique()->email;

        $this->postJson('api/auth/social/complete/' . $preUser->token, ['email' => $email])->assertOk();

        $this->assertDatabaseHas('users', ['email' => $email, 'first_name' => $preUser->first_name, 'last_name' => $preUser->last_name]);
        $this->assertFalse(User::query()->where('email', $email)->firstOrFail()->hasVerifiedEmail());
        Event::assertDispatched(AccountRegistered::class);
    }

    public function testCompleteRefusesAReturnUrlOutsideTheAllowList(): void
    {
        Event::fake([AccountRegistered::class]);
        $preUser = PreUser::factory()->create();

        $this->postJson('api/auth/social/complete/' . $preUser->token, ['email' => 'a@b.example', 'return_url' => 'https://evil.example/'])
            ->assertJsonValidationErrorFor('return_url');
        $this->postJson('api/auth/social/complete/' . $preUser->token, ['email' => 'a@b.example', 'return_url' => self::FRONT . '/verify'])
            ->assertOk();
    }

    public function testShouldNotCreateUserFromPreUserWhenTokenExpired(): void
    {
        Event::fake([AccountRegistered::class]);
        $preUser = PreUser::factory()->create();

        $this->travel(12)->minutes();

        $this->postJson('api/auth/social/complete/' . $preUser->token, ['email' => $this->faker->unique()->email])->assertStatus(400);
        Event::assertNotDispatched(AccountRegistered::class);
    }

    // ---- data integrity ---------------------------------------------------------------------------------

    public function testAProviderIdentityCanBeLinkedToOneAccountOnly(): void
    {
        SocialAccount::factory(['provider' => 'google', 'provider_id' => '99'])->create();

        $this->expectException(QueryException::class);
        DB::transaction(fn () => SocialAccount::factory(['provider' => 'google', 'provider_id' => '99'])->create());
    }

    public function testPruneRemovesOldHandoffRows(): void
    {
        $this->startLogin();
        $this->travel(2)->days();

        $this->artisan('ulams:auth:prune-login-handoff')->assertSuccessful();

        $this->assertSame(0, LoginHandoffState::query()->count());
    }
}
