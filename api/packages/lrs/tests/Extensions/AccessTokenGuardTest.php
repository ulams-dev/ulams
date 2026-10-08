<?php

namespace Ulams\Lrs\Tests\Extensions;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Models\User;
use Ulams\Lrs\Extensions\AccessTokenGuard;
use Ulams\Lrs\Models\BasicHttpCredentials;
use Ulams\Lrs\Tests\TestCase;
use Ulams\Lrs\Tests\Traits\XapiTesting;

class AccessTokenGuardTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers, XapiTesting;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('student');
    }

    protected function tearDown(): void
    {
        Passport::personalAccessTokensExpireIn(now()->addYear());
        parent::tearDown();
    }

    public function tokenDataProvider(): array
    {
        return [
            [
                'expire' => fn() => Passport::personalAccessTokensExpireIn(now()->addDay()),
                'assert' => fn($result) => $this->assertTrue($result)
            ],
            [
                'expire' => fn() => Passport::personalAccessTokensExpireIn(now()->subDay()),
                'assert' => fn($result) => $this->assertFalse($result)
            ],
        ];
    }

    /**
     * @dataProvider tokenDataProvider
     */
    public function testGuard($expireIn, $assert)
    {
        $expireIn();

        $token = $this->user->createToken("Ulams User Token")->accessToken;

        $assert($this->check("Basic {$token}"));
    }

    public function testValidTokenWithBearerSchemeAndUser(): void
    {
        Passport::personalAccessTokensExpireIn(now()->addDay());
        $token = $this->user->createToken('t')->accessToken;

        $guard = new AccessTokenGuard();
        $this->assertTrue($guard->check(null, $this->request("Bearer {$token}")));
        $this->assertSame($this->user->getKey(), $guard->user()?->getKey());
    }

    public function testForgedSignatureIsRejected(): void
    {
        [$jti] = $this->validJti();
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);

        $forged = JWT::encode(['jti' => $jti, 'sub' => (string) $this->user->getKey(), 'exp' => time() + 3600], $privateKey, 'RS256');

        $this->assertFalse($this->check("Basic {$forged}"));
    }

    public function testUnsignedTokenIsRejected(): void
    {
        [$jti] = $this->validJti();
        $b64 = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
        $unsigned = $b64(['typ' => 'JWT', 'alg' => 'none']) . '.' . $b64(['jti' => $jti, 'exp' => time() + 3600]) . '.';

        $this->assertFalse($this->check("Basic {$unsigned}"));
    }

    public function testTamperedPayloadIsRejected(): void
    {
        [$jti, $token] = $this->validJti();
        [$header, , $signature] = explode('.', $token);
        $payload = rtrim(strtr(base64_encode(json_encode(['jti' => $jti, 'exp' => time() + 999999])), '+/', '-_'), '=');

        $this->assertFalse($this->check("Basic {$header}.{$payload}.{$signature}"));
    }

    public function testRevokedTokenIsRejected(): void
    {
        [$jti, $token] = $this->validJti();
        Token::query()->whereKey($jti)->update(['revoked' => true]);

        $this->assertFalse($this->check("Basic {$token}"));
    }

    public function testMalformedHeadersAreRejected(): void
    {
        foreach (['', 'Basic', 'Basic xx', 'Bearer a.b.c', 'Token abc', 'Basic ' . base64_encode('nouser')] as $header) {
            $this->assertFalse($this->check($header), "Accepted: [$header]");
        }
    }

    public function testBasicCredentialsAreChecked(): void
    {
        $credentials = BasicHttpCredentials::create(['username' => 'lms', 'password' => Hash::make('s3cret')]);

        $this->assertTrue($this->check('Basic ' . base64_encode('lms:s3cret'), $credentials));
        $this->assertFalse($this->check('Basic ' . base64_encode('lms:wrong'), $credentials));
        $this->assertFalse($this->check('Basic ' . base64_encode('other:s3cret'), $credentials));
        $this->assertFalse($this->check('Basic ' . base64_encode('lms:s3cret')));
    }

    public function testXapiEndpointAnswers401ToForgedAndExpiredTokens(): void
    {
        $this->setUpXapi();

        $this->xapi('GET', '/statements')->assertOk();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $jti = JWT::jsonDecode(JWT::urlsafeB64Decode(explode('.', $this->token)[1]))->jti;
        $forged = JWT::encode(['jti' => $jti, 'exp' => time() + 3600], $privateKey, 'RS256');
        $this->xapi('GET', '/statements', null, ['Authorization' => "Basic {$forged}"])->assertUnauthorized();

        // Signed with the real key, but the exp claim is in the past.
        $expired = JWT::encode(['jti' => $jti, 'exp' => time() - 60], file_get_contents(Passport::keyPath('oauth-private.key')), 'RS256');
        $this->xapi('GET', '/statements', null, ['Authorization' => "Basic {$expired}"])->assertUnauthorized();

        // Valid signature and exp, but the token expired in the database.
        Token::query()->whereKey($jti)->update(['expires_at' => now()->subMinute()]);
        $this->xapi('GET', '/statements')->assertUnauthorized();

        $this->xapi('GET', '/statements', null, ['Authorization' => 'Basic ' . base64_encode('Ulams:Ulams')])->assertUnauthorized();
    }

    /**
     * @return array{0: string, 1: string} jti and the signed token
     */
    private function validJti(): array
    {
        Passport::personalAccessTokensExpireIn(now()->addDay());
        $token = $this->user->createToken('t')->accessToken;
        $this->assertTrue($this->check("Basic {$token}"));

        return [JWT::jsonDecode(JWT::urlsafeB64Decode(explode('.', $token)[1]))->jti, $token];
    }

    private function check(string $authorization, ?BasicHttpCredentials $credentials = null): bool
    {
        return (new AccessTokenGuard())->check($credentials, $this->request($authorization));
    }

    private function request(string $authorization): Request
    {
        $request = new Request();
        $request->headers->set('Authorization', $authorization);

        return $request;
    }
}
