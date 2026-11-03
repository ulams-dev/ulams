<?php

namespace Ulams\Identity\Tests\Feature;

use DOMDocument;
use DOMXPath;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Ulams\Auth\Models\LoginHandoffState;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Tests\Support\FakeSamlIdp;
use Ulams\Identity\Tests\Support\RunsOidcLogin;
use Ulams\Identity\Tests\Support\RunsSamlLogin;
use Ulams\Identity\Tests\TestCase;

class SamlStartAndMetadataTest extends TestCase
{
    use RunsOidcLogin;
    use RunsSamlLogin;

    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('md', 'urn:oasis:names:tc:SAML:2.0:metadata');
        $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $xp->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $xp->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');

        return $xp;
    }

    public function testTheStartRedirectCarriesASignedAuthnRequestAndTheLoginState(): void
    {
        $provider = $this->samlProvider();
        $this->newVerifier();

        $started = $this->start('acme-saml');
        $authn = $this->authnRequestOf($started);

        $this->assertStringContainsString('no-store', (string) $started->headers->get('Cache-Control'));
        $xp = $this->xpath($authn['xml']);
        $request = $xp->query('/samlp:AuthnRequest')->item(0);
        $this->assertSame(FakeSamlIdp::SSO_URL, $request->getAttribute('Destination'));
        $this->assertSame($this->acsUrl(), $request->getAttribute('AssertionConsumerServiceURL'));
        $this->assertSame('urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST', $request->getAttribute('ProtocolBinding'));
        $this->assertSame($this->spEntityId(), $xp->query('saml:Issuer', $request)->item(0)->textContent);
        $this->assertSame(0, $xp->query('samlp:RequestedAuthnContext')->length, 'no authentication context is demanded of the IdP');

        // the request is signed with the provider's SP key: the IdP can verify it with the certificate in the metadata
        $p = $authn['params'];
        $this->assertSame(XMLSecurityKey::RSA_SHA256, $p['SigAlg']);
        $signed = 'SAMLRequest=' . urlencode($p['SAMLRequest']) . '&RelayState=' . urlencode($p['RelayState']) . '&SigAlg=' . urlencode($p['SigAlg']);
        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'public']);
        $key->loadKey($provider->saml_sp_cert, false, true);
        $this->assertSame(1, $key->verifySignature($signed, base64_decode($p['Signature'])));

        // the RelayState is the signed single-use state, holding the request id for the ACS
        $row = LoginHandoffState::query()->firstOrFail();
        $this->assertSame('saml', $row->protocol);
        $this->assertSame('acme-saml', $row->provider);
        $this->assertSame($authn['id'], ((array) $row->data)['request_id']);
        $this->assertSame(self::RETURN_TO, $row->return_to);
    }

    public function testTheStartRefusesWhatTheOidcStartRefuses(): void
    {
        $this->samlProvider();

        $this->start('acme-saml', ['return_to' => 'https://evil.example.test/steal'])->assertStatus(422);
        $this->start('acme-saml', ['challenge' => 'short'])->assertStatus(422);
        $this->start('acme-saml', ['challenge' => null])->assertStatus(422);
        $this->start('unknown')->assertStatus(404);
        $this->assertSame(0, LoginHandoffState::query()->count());
    }

    public function testADisabledProviderCannotStartASignIn(): void
    {
        $this->samlProvider(['enabled' => false]);

        $this->start('acme-saml')->assertStatus(404);
    }

    public function testAProviderWithoutCertificatesIsNotAvailableAndLeavesNoState(): void
    {
        $this->samlProvider(['saml_idp_certs' => []]);

        $this->start('acme-saml')->assertStatus(502);
        $this->assertSame(0, LoginHandoffState::query()->count());
    }

    public function testBothProtocolsAreOfferedOnTheLoginPage(): void
    {
        $this->provider(['slug' => 'oidc-one', 'name' => 'A OIDC']);
        $this->samlProvider(['name' => 'B SAML']);
        $this->samlProvider(['slug' => 'off', 'name' => 'Off', 'enabled' => false]);

        $list = $this->getJson('/api/identity/providers')->assertOk()->json('data');

        $this->assertSame([
            ['slug' => 'oidc-one', 'name' => 'A OIDC', 'protocol' => 'oidc'],
            ['slug' => 'acme-saml', 'name' => 'B SAML', 'protocol' => 'saml'],
        ], $list);
    }

    public function testTheMetadataDescribesTheServiceProvider(): void
    {
        $provider = $this->samlProvider();

        $response = $this->get('/api/identity/saml/acme-saml/metadata')->assertOk();

        $this->assertStringStartsWith('application/samlmetadata+xml', (string) $response->headers->get('Content-Type'));
        $xp = $this->xpath($response->getContent());
        $this->assertSame($this->spEntityId(), $xp->query('/md:EntityDescriptor')->item(0)->getAttribute('entityID'), 'the entity id is the metadata URL (plan question 4)');
        $sp = $xp->query('/md:EntityDescriptor/md:SPSSODescriptor')->item(0);
        $this->assertSame('true', $sp->getAttribute('AuthnRequestsSigned'));
        $this->assertSame('true', $sp->getAttribute('WantAssertionsSigned'));
        $acs = $xp->query('md:AssertionConsumerService', $sp)->item(0);
        $this->assertSame($this->acsUrl(), $acs->getAttribute('Location'));
        $this->assertSame('urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST', $acs->getAttribute('Binding'));
        $slo = $xp->query('md:SingleLogoutService', $sp)->item(0);
        $this->assertSame($this->samlBase() . '/api/identity/saml/acme-saml/slo', $slo->getAttribute('Location'));
        $certs = [];
        foreach ($xp->query('md:KeyDescriptor/ds:KeyInfo/ds:X509Data/ds:X509Certificate', $sp) as $node) {
            $certs[] = $node->textContent;
        }
        $this->assertContains(FakeSamlIdp::certBody($provider->saml_sp_cert), array_unique($certs));
        $this->assertStringNotContainsString('PRIVATE KEY', $response->getContent());
        $this->assertStringNotContainsString(FakeSamlIdp::certBody($provider->saml_sp_key), $response->getContent());
    }

    public function testThePreparedNextCertificateIsPublishedNextToTheCurrentOne(): void
    {
        $provider = $this->samlProvider();
        $next = FakeSamlIdp::pair('sp-1');
        $provider->forceFill(['saml_sp_next_key' => $next['key'], 'saml_sp_next_cert' => $next['cert']])->save();

        $xml = $this->get('/api/identity/saml/acme-saml/metadata')->assertOk()->getContent();

        $this->assertStringContainsString(FakeSamlIdp::certBody($next['cert']), $xml);
        $this->assertStringContainsString(FakeSamlIdp::certBody(FakeSamlIdp::pair('sp-0')['cert']), $xml);
        $this->assertStringNotContainsString('PRIVATE KEY', $xml);
    }

    public function testTheCertificateIsOfferedForEncryptionOnlyWhenTheProviderWantsEncryptedAssertions(): void
    {
        $provider = $this->samlProvider();

        $uses = function (): array {
            $xp = $this->xpath($this->get('/api/identity/saml/acme-saml/metadata')->assertOk()->getContent());
            $uses = [];
            foreach ($xp->query('//md:SPSSODescriptor/md:KeyDescriptor') as $node) {
                $uses[] = $node->getAttribute('use');
            }

            return $uses;
        };

        // an IdP must not start encrypting by itself (its default may be a key transport this stack cannot open)
        $this->assertSame(['signing'], $uses());

        $provider->forceFill(['saml' => array_replace((array) $provider->saml, ['want_encrypted_assertions' => true])])->save();
        $this->assertEqualsCanonicalizing(['signing', 'encryption'], $uses());
    }

    public function testTheMetadataIsServedBeforeTheProviderIsEnabledSoTheIdpCanBeRegistered(): void
    {
        $this->samlProvider(['enabled' => false]);

        $this->get('/api/identity/saml/acme-saml/metadata')->assertOk();
    }

    public function testTheEntityIdFollowsTheHostOfTheTenant(): void
    {
        $this->samlProvider();

        $xml = $this->get('http://tenant-b.example.test/api/identity/saml/acme-saml/metadata')->assertOk()->getContent();

        $this->assertStringContainsString('entityID="http://tenant-b.example.test/api/identity/saml/acme-saml/metadata"', $xml);
        $this->assertStringNotContainsString('localhost', $xml);
    }

    public function testMetadataOfAnUnknownOrNonSamlProviderIs404(): void
    {
        $this->provider();

        $this->get('/api/identity/saml/acme/metadata')->assertStatus(404);
        $this->get('/api/identity/saml/nope/metadata')->assertStatus(404);
    }

    public function testAnOidcProviderHasNoSamlStartThroughTheAcs(): void
    {
        $this->provider();
        $authn = ['id' => '_x'];

        // an OIDC provider's slug at the SAML ACS: no state of protocol saml exists for it
        $this->postAcs($this->responseFor($authn), 'a.b.c', 'acme')->assertStatus(400);
        $this->assertSame(0, IdentityProvider::query()->where('protocol', 'saml')->count());
    }
}
