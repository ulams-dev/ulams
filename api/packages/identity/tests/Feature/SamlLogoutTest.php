<?php

namespace Ulams\Identity\Tests\Feature;

use DOMDocument;
use DOMXPath;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Models\IdentitySession;
use Ulams\Identity\Tests\Support\FakeSamlIdp;
use Ulams\Identity\Tests\Support\RunsOidcLogin;
use Ulams\Identity\Tests\Support\RunsSamlLogin;
use Ulams\Identity\Tests\TestCase;

/** Single logout (plan 4.6): started here, started by the identity provider, and every way a message can be wrong. */
class SamlLogoutTest extends TestCase
{
    use RunsOidcLogin;
    use RunsSamlLogin;

    private const SLO = 'http://localhost/api/identity/saml/acme-saml/slo';

    protected function setUp(): void
    {
        parent::setUp();
        $this->samlProvider();
    }

    /** Signs a person in through the fake IdP and returns their access token. */
    private function login(string $nameId = 'user-1', string $sessionIndex = '_session-1', string $email = 'ada@example.com'): string
    {
        $this->newVerifier();
        $result = $this->samlSignIn(['name_id' => $nameId, 'session_index' => $sessionIndex, 'attributes' => ['email' => $email]]);

        return (string) $this->exchange($this->returned($result['response'])['code'])->assertOk()->json('data.token');
    }

    private function authorized(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function profileStatus(string $token): int
    {
        return $this->authorized($token)->getJson('api/profile/me')->getStatusCode();
    }

    /** @return array<string,string> */
    private function logoutRequestParams(string $url): array
    {
        return $this->queryOf($url);
    }

    public function testSigningOutStartsSingleLogoutAtTheIdentityProvider(): void
    {
        $token = $this->login();
        $this->assertSame(200, $this->profileStatus($token));

        $response = $this->authorized($token)->postJson('api/identity/saml/acme-saml/logout', ['return_to' => 'https://app.example.test/goodbye'])->assertOk();

        $url = (string) $response->json('data.redirect_url');
        $this->assertStringStartsWith(FakeSamlIdp::SLO_URL . '?', $url);
        $this->assertSame(401, $this->profileStatus($token), 'the token of this academy is revoked at once');

        $params = $this->logoutRequestParams($url);
        $xml = gzinflate((string) base64_decode($params['SAMLRequest']));
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $xp->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');
        $this->assertSame('user-1', $xp->query('//saml:NameID')->item(0)->textContent);
        $this->assertSame('_session-1', $xp->query('//samlp:SessionIndex')->item(0)->textContent);
        $this->assertSame(FakeSamlIdp::SLO_URL, $dom->documentElement->getAttribute('Destination'));
        $this->assertSame($this->spEntityId(), $xp->query('//saml:Issuer')->item(0)->textContent);

        // signed with the provider's SP key
        $signed = 'SAMLRequest=' . urlencode($params['SAMLRequest']) . '&SigAlg=' . urlencode($params['SigAlg']);
        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'public']);
        $key->loadKey(FakeSamlIdp::pair('sp-0')['cert'], false, true);
        $this->assertSame(1, $key->verifySignature($signed, base64_decode($params['Signature'])));
        $this->assertNotNull(IdentitySession::query()->firstOrFail()->ended_at);
    }

    public function testTheIdentityProvidersLogoutResponseSendsTheBrowserToTheReturnUrlOnce(): void
    {
        $token = $this->login();
        $url = $this->authorized($token)->postJson('api/identity/saml/acme-saml/logout', ['return_to' => 'https://app.example.test/goodbye'])->json('data.redirect_url');
        $id = $this->idOf(gzinflate((string) base64_decode($this->queryOf($url)['SAMLRequest'])));

        $message = $this->samlIdp->logoutMessage(['type' => 'response', 'destination' => self::SLO, 'in_response_to' => $id]);
        $first = $this->get('http://localhost/api/identity/saml/acme-saml/slo?' . $message['query']);

        $first->assertRedirect('https://app.example.test/goodbye');
        $this->get('http://localhost/api/identity/saml/acme-saml/slo?' . $message['query'])->assertStatus(400);
        $this->assertSame(1, IdentityAudit::query()->where('action', 'saml.logout')->where('data->kind', 'response')->count());
    }

    public function testAnAnswerToAnOldLogoutRequestIsRefused(): void
    {
        $this->pendingLogout('_old', 'https://app.example.test/');
        IdentitySession::query()->firstOrFail()->forceFill(['ended_at' => now()->subHour()])->save();

        $message = $this->samlIdp->logoutMessage(['type' => 'response', 'destination' => self::SLO, 'in_response_to' => '_old']);
        $this->get('http://localhost/api/identity/saml/acme-saml/slo?' . $message['query'])->assertStatus(400);
    }

    public function testALogoutResponseToARequestWeNeverSentIsRefused(): void
    {
        $message = $this->samlIdp->logoutMessage(['type' => 'response', 'destination' => self::SLO, 'in_response_to' => '_never-sent']);

        $this->get('http://localhost/api/identity/saml/acme-saml/slo?' . $message['query'])->assertStatus(400);
    }

    public function testALogoutResponseThatIsNotSuccessfulOrNotSignedIsRefused(): void
    {
        $this->pendingLogout('_r1', 'https://app.example.test/');
        $failed = $this->samlIdp->logoutMessage(['type' => 'response', 'destination' => self::SLO, 'in_response_to' => '_r1', 'status' => 'urn:oasis:names:tc:SAML:2.0:status:Responder']);
        $this->get('http://localhost/api/identity/saml/acme-saml/slo?' . $failed['query'])->assertStatus(400);

        $unsigned = $this->samlIdp->logoutMessage(['type' => 'response', 'destination' => self::SLO, 'in_response_to' => '_r1', 'sign' => false]);
        $this->get('http://localhost/api/identity/saml/acme-saml/slo?' . $unsigned['query'])->assertStatus(400);
        $this->assertNotNull(IdentitySession::query()->where('logout_request_hash', hash('sha256', '_r1'))->first(), 'a refused response does not use up the request');
    }

    /** A session whose logout request `$id` is waiting for the identity provider's answer. */
    private function pendingLogout(string $id, string $returnTo): void
    {
        $this->login();
        IdentitySession::query()->firstOrFail()->forceFill(['ended_at' => now(), 'logout_request_hash' => hash('sha256', $id), 'logout_return_to' => $returnTo])->save();
    }

    private function idOf(string $xml): string
    {
        preg_match('/\bID="([^"]+)"/', $xml, $m);

        return $m[1];
    }

    public function testSigningOutWithoutAnIdpSessionJustRevokesTheToken(): void
    {
        $user = $this->student();
        $token = $user->createToken('plain')->accessToken;
        $this->assertSame(200, $this->profileStatus($token));

        $response = $this->authorized($token)->postJson('api/identity/saml/acme-saml/logout')->assertOk();

        $this->assertNull($response->json('data.redirect_url'));
        $this->assertSame(401, $this->profileStatus($token));
    }

    public function testAProviderWithoutSingleLogoutOnlyRevokesTheToken(): void
    {
        \Ulams\Identity\Models\IdentityProvider::query()->firstOrFail()->forceFill(['saml' => array_replace((array) \Ulams\Identity\Models\IdentityProvider::query()->firstOrFail()->saml, ['slo_url' => null])])->save();
        $token = $this->login();

        $response = $this->authorized($token)->postJson('api/identity/saml/acme-saml/logout')->assertOk();

        $this->assertNull($response->json('data.redirect_url'));
        $this->assertSame(401, $this->profileStatus($token));
    }

    public function testTheReturnUrlOfALogoutMustBeAnOriginOfTheAcademy(): void
    {
        $token = $this->login();

        $this->authorized($token)->postJson('api/identity/saml/acme-saml/logout', ['return_to' => 'https://evil.example.test/'])->assertStatus(422);
        $this->assertSame(200, $this->profileStatus($token), 'nothing happened');
    }

    public function testLogoutNeedsAToken(): void
    {
        $this->postJson('api/identity/saml/acme-saml/logout')->assertStatus(401);
    }

    // --- IdP-initiated --------------------------------------------------------------------------------------------

    public function testALogoutRequestFromTheIdentityProviderEndsTheTokensOfThatSession(): void
    {
        $one = $this->login('user-1', '_session-1', 'ada@example.com');
        $two = $this->login('user-1', '_session-2', 'ada@example.com');
        $other = $this->login('user-2', '_session-3', 'grace@example.com');

        $message = $this->samlIdp->logoutMessage(['destination' => self::SLO, 'name_id' => 'user-1', 'session_index' => '_session-1', 'relay_state' => 'idp-relay']);
        $response = $this->get('http://localhost/api/identity/saml/acme-saml/slo?' . $message['query']);

        $this->assertSame(401, $this->profileStatus($one));
        $this->assertSame(200, $this->profileStatus($two), 'another session of the same person stays');
        $this->assertSame(200, $this->profileStatus($other));

        // the answer is a signed LogoutResponse to the IdP, InResponseTo the request
        $location = (string) $response->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith(FakeSamlIdp::SLO_URL . '?', $location);
        $params = $this->queryOf($location);
        $this->assertSame('idp-relay', $params['RelayState']);
        $xml = gzinflate((string) base64_decode($params['SAMLResponse']));
        $this->assertStringContainsString('InResponseTo="' . $this->idOf($this->decodeRequest($message['query'])) . '"', $xml);
        $this->assertStringContainsString('status:Success', $xml);
        $signed = 'SAMLResponse=' . urlencode($params['SAMLResponse']) . '&RelayState=' . urlencode($params['RelayState']) . '&SigAlg=' . urlencode($params['SigAlg']);
        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'public']);
        $key->loadKey(FakeSamlIdp::pair('sp-0')['cert'], false, true);
        $this->assertSame(1, $key->verifySignature($signed, base64_decode($params['Signature'])));

        $row = IdentityAudit::query()->where('action', 'saml.logout')->firstOrFail();
        $this->assertSame(['kind' => 'request', 'revoked' => 1], $row->data);
    }

    private function decodeRequest(string $query): string
    {
        return gzinflate((string) base64_decode($this->queryOf('?' . $query)['SAMLRequest']));
    }

    public function testALogoutRequestWithoutSessionIndexEndsEveryTokenOfThePerson(): void
    {
        $one = $this->login('user-1', '_session-1');
        $two = $this->login('user-1', '_session-2');

        $message = $this->samlIdp->logoutMessage(['destination' => self::SLO, 'name_id' => 'user-1']);
        $this->get('http://localhost/api/identity/saml/acme-saml/slo?' . $message['query'])->assertRedirect();

        $this->assertSame(401, $this->profileStatus($one));
        $this->assertSame(401, $this->profileStatus($two));
    }

    public function testALogoutRequestForAnUnknownPersonIsAnsweredAndChangesNothing(): void
    {
        $token = $this->login();

        $message = $this->samlIdp->logoutMessage(['destination' => self::SLO, 'name_id' => 'nobody']);
        $this->get('http://localhost/api/identity/saml/acme-saml/slo?' . $message['query'])->assertRedirect();

        $this->assertSame(200, $this->profileStatus($token));
    }

    /** @return array<string,array{0:array<string,mixed>,1:string}> */
    public static function badLogoutRequests(): array
    {
        $slo = self::SLO;

        return [
            'unsigned' => [['destination' => $slo, 'sign' => false], 'unsigned'],
            'signed by another key' => [['destination' => $slo, 'key' => FakeSamlIdp::pair('attacker')], 'signature_invalid'],
            'wrong issuer' => [['destination' => $slo, 'issuer' => 'https://evil.example.test/idp'], 'issuer_mismatch'],
            'wrong destination' => [['destination' => 'http://localhost/api/identity/saml/other/slo'], 'destination_mismatch'],
            'no destination' => [['destination' => null], 'destination_mismatch'],
            'expired' => [['destination' => $slo, 'not_on_or_after' => time() - 600], 'expired'],
            'issued long ago' => [['destination' => $slo, 'issued' => time() - 3600, 'not_on_or_after' => null], 'assertion_too_old'],
            'sha1' => [['destination' => $slo, 'sig_alg' => XMLSecurityKey::RSA_SHA1], 'sha1_not_allowed'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badLogoutRequests')]
    public function testABadLogoutRequestEndsNothing(array $options, string $reason): void
    {
        $token = $this->login();

        $message = $this->samlIdp->logoutMessage($options + ['name_id' => 'user-1']);
        $this->get('http://localhost/api/identity/saml/acme-saml/slo?' . $message['query'])->assertStatus(400);

        $this->assertSame(200, $this->profileStatus($token));
        $this->assertSame($reason, IdentityAudit::query()->where('action', 'saml.logout_failed')->firstOrFail()->data['reason']);
    }

    public function testATamperedLogoutRequestEndsNothing(): void
    {
        $token = $this->login();
        $message = $this->samlIdp->logoutMessage(['destination' => self::SLO, 'name_id' => 'user-1']);
        // the attacker swaps the message but keeps the signature
        $evil = $this->samlIdp->logoutMessage(['destination' => self::SLO, 'name_id' => 'user-1', 'session_index' => '_session-1']);
        parse_str($message['query'], $good);
        parse_str($evil['query'], $bad);
        $query = 'SAMLRequest=' . urlencode($bad['SAMLRequest']) . '&SigAlg=' . urlencode($good['SigAlg']) . '&Signature=' . urlencode($good['Signature']);

        $this->get('http://localhost/api/identity/saml/acme-saml/slo?' . $query)->assertStatus(400);

        $this->assertSame(200, $this->profileStatus($token));
    }

    public function testALogoutRequestWorksOnce(): void
    {
        $this->login();
        $message = $this->samlIdp->logoutMessage(['destination' => self::SLO, 'name_id' => 'user-1', 'id' => '_fixed-logout-id']);

        $this->get('http://localhost/api/identity/saml/acme-saml/slo?' . $message['query'])->assertRedirect();
        $this->get('http://localhost/api/identity/saml/acme-saml/slo?' . $message['query'])->assertStatus(400);

        $this->assertSame('replayed', IdentityAudit::query()->where('action', 'saml.logout_failed')->firstOrFail()->data['reason']);
    }

    public function testALogoutRequestInThePostBindingNeedsAnEnvelopedSignature(): void
    {
        $token = $this->login();
        $this->post('http://localhost/api/identity/saml/acme-saml/slo', $this->samlIdp->logoutMessage(['destination' => self::SLO, 'name_id' => 'user-1', 'binding' => 'post', 'sign' => false])['form'])->assertStatus(400);
        $this->assertSame(200, $this->profileStatus($token));

        $this->post('http://localhost/api/identity/saml/acme-saml/slo', $this->samlIdp->logoutMessage(['destination' => self::SLO, 'name_id' => 'user-1', 'binding' => 'post', 'key' => FakeSamlIdp::pair('attacker')])['form'])->assertStatus(400);
        $this->assertSame(200, $this->profileStatus($token));

        $this->post('http://localhost/api/identity/saml/acme-saml/slo', $this->samlIdp->logoutMessage(['destination' => self::SLO, 'name_id' => 'user-1', 'binding' => 'post'])['form'])->assertRedirect();
        $this->assertSame(401, $this->profileStatus($token));
    }

    public function testASignatureThatDoesNotCoverTheLogoutRequestIsRefused(): void
    {
        $token = $this->login();
        // a signed element is wrapped inside the request, the request itself is not signed
        $mutate = function (DOMDocument $dom): void {
            $root = $dom->documentElement;
            $signature = $dom->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature')->item(0);
            $extensions = $dom->createElementNS('urn:oasis:names:tc:SAML:2.0:protocol', 'samlp:Extensions');
            $root->insertBefore($extensions, $root->firstChild);
            $extensions->appendChild($signature);
        };

        $form = $this->samlIdp->logoutMessage(['destination' => self::SLO, 'name_id' => 'user-1', 'binding' => 'post', 'mutate' => $mutate])['form'];
        $this->post('http://localhost/api/identity/saml/acme-saml/slo', $form)->assertStatus(400);

        $this->assertSame(200, $this->profileStatus($token));
    }

    public function testASloEndpointWithoutAMessageOrWithBothIsRefused(): void
    {
        $this->get('http://localhost/api/identity/saml/acme-saml/slo')->assertStatus(400);
        $this->post('http://localhost/api/identity/saml/acme-saml/slo', [])->assertStatus(400);
        $this->get('http://localhost/api/identity/saml/acme-saml/slo?SAMLRequest=a&SAMLResponse=b')->assertStatus(400);
        $this->get('http://localhost/api/identity/saml/nope/slo?SAMLRequest=a')->assertStatus(404);
    }
}
