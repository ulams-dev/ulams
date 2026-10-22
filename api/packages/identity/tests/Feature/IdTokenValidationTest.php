<?php

namespace Ulams\Identity\Tests\Feature;

use Firebase\JWT\JWT;
use Ulams\Auth\Models\User;
use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Models\IdentityLink;
use Ulams\Identity\Tests\Support\FakeIdp;
use Ulams\Identity\Tests\Support\RunsOidcLogin;
use Ulams\Identity\Tests\TestCase;

/** Every way an ID token can be wrong ends in "provider_failed" for the browser, a reason in the audit, and no account. */
class IdTokenValidationTest extends TestCase
{
    use RunsOidcLogin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider();
    }

    private function assertRefused(array $result, string $reason): void
    {
        $this->assertSame(['error' => 'provider_failed'], $this->queryOf((string) $result['response']->headers->get('Location')));
        $row = IdentityAudit::query()->where('action', 'sso.login_failed')->latest('id')->firstOrFail();
        $this->assertSame($reason, $row->data['reason']);
        $this->assertFalse(User::query()->where('email', 'ada@example.com')->exists());
        $this->assertSame(0, IdentityLink::query()->count());
    }

    public function testASignatureFromAnotherKeyIsRefused(): void
    {
        // signed with a key we never published, claiming the kid of one we did
        $attacker = new FakeIdp();

        $this->assertRefused($this->signIn([], ['secret' => $attacker->privatePem('rsa1')]), 'bad_signature');
    }

    public function testAnotherAudienceIsRefused(): void
    {
        $this->assertRefused($this->signIn(['aud' => 'another-client']), 'wrong_audience');
    }

    public function testSeveralAudiencesNeedOurClientAsAuthorizedParty(): void
    {
        $this->assertRefused($this->signIn(['aud' => [FakeIdp::CLIENT_ID, 'other-api']]), 'wrong_authorized_party');
    }

    public function testSeveralAudiencesAreFineWithTheRightAuthorizedParty(): void
    {
        $this->signIn(['aud' => [FakeIdp::CLIENT_ID, 'other-api'], 'azp' => FakeIdp::CLIENT_ID]);

        $this->assertTrue(User::query()->where('email', 'ada@example.com')->exists());
    }

    public function testAnotherAuthorizedPartyIsRefusedEvenWithOneAudience(): void
    {
        $this->assertRefused($this->signIn(['azp' => 'someone-else']), 'wrong_authorized_party');
    }

    public function testAnotherIssuerIsRefused(): void
    {
        $this->assertRefused($this->signIn(['iss' => 'https://idp.example.test/realms/other']), 'wrong_issuer');
        $this->newVerifier();
        $this->assertRefused($this->signIn(['iss' => FakeIdp::ISSUER . '/']), 'wrong_issuer');
    }

    public function testAnExpiredTokenIsRefused(): void
    {
        $this->assertRefused($this->signIn(['exp' => time() - 120, 'iat' => time() - 400]), 'expired');
    }

    public function testASmallClockSkewIsTolerated(): void
    {
        $this->signIn(['exp' => time() - 30, 'iat' => time() - 400]);

        $this->assertTrue(User::query()->where('email', 'ada@example.com')->exists());
    }

    public function testATokenThatIsNotValidYetIsRefused(): void
    {
        $this->assertRefused($this->signIn(['nbf' => time() + 600]), 'not_yet_valid');
        $this->newVerifier();
        $this->assertRefused($this->signIn(['iat' => time() + 600]), 'not_yet_valid');
    }

    public function testATokenWithoutExpiryIsRefused(): void
    {
        $this->assertRefused($this->signIn(['exp' => null]), 'missing_claim');
    }

    public function testATokenWithoutSubjectIsRefused(): void
    {
        $this->assertRefused($this->signIn(['sub' => null]), 'missing_claim');
    }

    public function testAnotherNonceIsRefused(): void
    {
        $this->assertRefused($this->signIn(['nonce' => 'not-the-one-we-sent']), 'nonce_mismatch');
    }

    public function testATokenWithoutNonceIsRefused(): void
    {
        $this->assertRefused($this->signIn(['nonce' => null]), 'nonce_mismatch');
    }

    public function testAlgNoneIsRefused(): void
    {
        $started = $this->start();
        $auth = $this->queryOf($started->headers->get('Location'));
        $token = FakeIdp::unsigned(['alg' => 'none', 'typ' => 'JWT'], [
            'iss' => FakeIdp::ISSUER, 'aud' => FakeIdp::CLIENT_ID, 'sub' => 's', 'exp' => time() + 300, 'nonce' => $auth['nonce'], 'email' => 'ada@example.com', 'email_verified' => true,
        ]);
        $this->idp->tokenBody = ['id_token' => $token, 'access_token' => 'a'];

        $response = $this->answerCallback('acme', $auth['state']);

        $this->assertRefused(['response' => $response], 'bad_algorithm');
    }

    public function testAHmacTokenSignedWithThePublicKeyIsRefused(): void
    {
        // the classic algorithm confusion: HS256 with the RSA public key as the shared secret
        $started = $this->start();
        $auth = $this->queryOf($started->headers->get('Location'));
        $token = JWT::encode([
            'iss' => FakeIdp::ISSUER, 'aud' => FakeIdp::CLIENT_ID, 'sub' => 's', 'exp' => time() + 300, 'nonce' => $auth['nonce'], 'email' => 'ada@example.com', 'email_verified' => true,
        ], $this->idp->publicPem('rsa1'), 'HS256', 'rsa1');
        $this->idp->tokenBody = ['id_token' => $token, 'access_token' => 'a'];

        $this->assertRefused(['response' => $this->answerCallback('acme', $auth['state'])], 'bad_algorithm');
    }

    public function testAnEcKeyCannotVerifyAnRsaAlgorithmAndTheOtherWayRound(): void
    {
        // header says RS256 but the kid points at the EC key
        $started = $this->start();
        $auth = $this->queryOf($started->headers->get('Location'));
        $token = $this->idp->idToken($auth['nonce'], [], 'RS256', 'rsa1');
        [$h, $p, $s] = explode('.', $token);
        $header = json_decode(base64_decode(strtr($h, '-_', '+/')), true);
        $header['kid'] = 'ec1';
        $forged = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=') . '.' . $p . '.' . $s;
        $this->idp->tokenBody = ['id_token' => $forged, 'access_token' => 'a'];

        $this->assertRefused(['response' => $this->answerCallback('acme', $auth['state'])], 'bad_algorithm');
    }

    public function testAnEcSignedTokenIsAccepted(): void
    {
        $this->signIn([], ['alg' => 'ES256', 'kid' => 'ec1']);

        $this->assertTrue(User::query()->where('email', 'ada@example.com')->exists());
    }

    public function testAnUnknownKidRefetchesTheKeysOnceAndThenFails(): void
    {
        $this->idp->addRsa('rsa-unknown');
        // the provider does not publish rsa-unknown
        $result = $this->signIn([], ['kid' => 'rsa-unknown']);

        $this->assertRefused($result, 'unknown_key');
        $this->assertSame(2, $this->idp->jwksFetches);
    }

    public function testARotatedKeyIsPickedUpByTheRefetch(): void
    {
        $this->signIn([], ['kid' => 'rsa1', 'claims' => []]);
        $this->assertSame(1, $this->idp->jwksFetches);

        $this->idp->addRsa('rsa2');
        $this->idp->jwks[] = $this->idp->publicJwk('rsa2');
        $this->newVerifier();
        $this->signIn(['sub' => 'subject-2', 'email' => 'bob@example.com'], ['kid' => 'rsa2']);

        $this->assertSame(2, $this->idp->jwksFetches);
        $this->assertTrue(User::query()->where('email', 'bob@example.com')->exists());
    }

    public function testKeysAndDiscoveryAreCachedBetweenLogins(): void
    {
        // the first login creates the account, and creating a user flushes the whole cache (model fields)
        $this->signIn();
        $this->newVerifier();
        $this->signIn();
        $this->assertSame([2, 2], [$this->idp->jwksFetches, $this->idp->discoveryFetches]);

        $this->newVerifier();
        $this->signIn();
        $this->assertSame([2, 2], [$this->idp->jwksFetches, $this->idp->discoveryFetches]);
    }

    public function testAKeyMarkedForEncryptionIsNotUsedForSignatures(): void
    {
        $this->idp->jwks = array_map(fn (array $k) => $k['kid'] === 'rsa1' ? $k + ['use' => 'enc'] : $k, [$this->idp->publicJwk('rsa1')]);
        $this->idp->jwks[0]['use'] = 'enc';

        $this->assertRefused($this->signIn(), 'bad_algorithm');
    }

    public function testAnIdTokenThatIsNotAJwtIsRefused(): void
    {
        $this->assertRefused($this->signIn([], ['id_token' => 'not.a.jwt.at.all']), 'malformed_token');
        $this->newVerifier();
        $this->assertRefused($this->signIn([], ['id_token' => str_repeat('a', 20000)]), 'malformed_token');
    }

    public function testATokenEndpointErrorIsRefused(): void
    {
        $started = $this->start();
        $auth = $this->queryOf($started->headers->get('Location'));
        $this->idp->tokenBody = ['error' => 'invalid_grant'];
        $this->idp->tokenStatus = 400;

        $this->assertRefused(['response' => $this->answerCallback('acme', $auth['state'])], 'token_request_failed');
    }

    public function testAnAnswerWithoutAnIdTokenIsRefused(): void
    {
        $started = $this->start();
        $auth = $this->queryOf($started->headers->get('Location'));
        $this->idp->tokenBody = ['access_token' => 'a', 'token_type' => 'Bearer'];

        $this->assertRefused(['response' => $this->answerCallback('acme', $auth['state'])], 'no_id_token');
    }

    public function testACallbackWithoutACodeIsRefused(): void
    {
        $started = $this->start();
        $auth = $this->queryOf($started->headers->get('Location'));

        $response = $this->answerCallback('acme', $auth['state'], ['code' => null]);

        $this->assertRefused(['response' => $response], 'no_code');
    }
}
