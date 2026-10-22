<?php

namespace Ulams\Identity\Tests\Unit;

use OneLogin\Saml2\Utils;
use Ulams\Identity\Exceptions\SamlException;
use Ulams\Identity\Services\Saml\IdpMetadata;
use Ulams\Identity\Services\Saml\SamlContext;
use Ulams\Identity\Services\Saml\SamlService;
use Ulams\Identity\Services\Saml\SamlXml;
use Ulams\Identity\Tests\Support\FakeSamlIdp;
use Ulams\Identity\Tests\TestCase;

/** The settings the library is configured with, the URLs, and the checks that run before it. */
class SamlSettingsTest extends TestCase
{
    public function testTheSecurityPostureIsStrict(): void
    {
        $provider = $this->samlProvider();

        $settings = app(SamlService::class)->settingsArray($provider, new SamlContext('https', 'api.academy.test', 443));
        $security = $settings['security'];

        $this->assertTrue($settings['strict']);
        $this->assertTrue($security['wantAssertionsSigned']);
        $this->assertTrue($security['wantXMLValidation']);
        $this->assertTrue($security['rejectUnsolicitedResponsesWithInResponseTo']);
        $this->assertTrue($security['destinationStrictlyMatches']);
        $this->assertFalse($security['relaxDestinationValidation']);
        $this->assertFalse($security['allowRepeatAttributeName']);
        $this->assertTrue($security['authnRequestsSigned']);
        $this->assertTrue($security['logoutRequestSigned']);
        $this->assertTrue($security['logoutResponseSigned']);
        $this->assertFalse($security['wantAssertionsEncrypted']);
        $this->assertSame('http://www.w3.org/2001/04/xmldsig-more#rsa-sha256', $security['signatureAlgorithm']);
        $this->assertSame('http://www.w3.org/2001/04/xmlenc#sha256', $security['digestAlgorithm']);
    }

    public function testTheUrlsDeriveFromTheTenantHostAndPort(): void
    {
        $provider = $this->samlProvider();
        $service = app(SamlService::class);

        $https = $service->settingsArray($provider, new SamlContext('https', 'api.academy.test', 443));
        $dev = $service->settingsArray($provider, new SamlContext('http', 'ulams.test', 18000));

        $this->assertSame('https://api.academy.test/api/identity/saml/acme-saml/metadata', $https['sp']['entityId']);
        $this->assertSame('https://api.academy.test/api/identity/saml/acme-saml/acs', $https['sp']['assertionConsumerService']['url']);
        $this->assertSame('http://ulams.test:18000/api/identity/saml/acme-saml/slo', $dev['sp']['singleLogoutService']['url']);
    }

    public function testOnlyThisProvidersCertificatesAreTrustedAndTheEncryptionFlagFollowsTheProvider(): void
    {
        $provider = $this->samlProvider(saml: ['want_encrypted_assertions' => true]);

        $settings = app(SamlService::class)->settingsArray($provider, new SamlContext('https', 'a.test', 443));

        $this->assertSame([FakeSamlIdp::certBody($this->samlIdp->signing['cert'])], $settings['idp']['x509certMulti']['signing']);
        $this->assertSame(FakeSamlIdp::ENTITY_ID, $settings['idp']['entityId']);
        $this->assertTrue($settings['security']['wantAssertionsEncrypted']);
        $this->assertArrayNotHasKey('x509certNew', $settings['sp']);
    }

    public function testASettingsFailureIsAFixedReasonNotALibraryMessage(): void
    {
        $provider = $this->samlProvider(['saml_idp_certs' => []]);

        try {
            app(SamlService::class)->settings($provider, new SamlContext('https', 'a.test', 443));
            $this->fail('expected a refusal');
        } catch (SamlException $e) {
            $this->assertSame('misconfigured', $e->reason);
        }
    }

    public function testTheLibraryStateIsSetForOneCallAndRestored(): void
    {
        $_SERVER['REQUEST_URI'] = '/before';
        $ctx = new SamlContext('https', 'api.academy.test', 8443);

        $inside = $ctx->run('/api/identity/saml/x/acs', fn () => [Utils::getSelfRoutedURLNoQuery(), $_SERVER['REQUEST_URI']]);

        $this->assertSame(['https://api.academy.test:8443/api/identity/saml/x/acs', '/api/identity/saml/x/acs'], $inside);
        $this->assertSame('/before', $_SERVER['REQUEST_URI']);
        unset($_SERVER['REQUEST_URI']);
    }

    public function testTheContextRestoresTheStateWhenTheCallThrows(): void
    {
        $_SERVER['REQUEST_URI'] = '/before';
        try {
            (new SamlContext('https', 'a.test', 443))->run('/inside', function () {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame('/before', $_SERVER['REQUEST_URI']);
        unset($_SERVER['REQUEST_URI']);
    }

    public function testDecodeEnforcesSizeEncodingAndNoDoctype(): void
    {
        $reason = function (?string $input, bool $inflate = false): ?string {
            try {
                SamlXml::decode($input, $inflate);

                return null;
            } catch (SamlException $e) {
                return $e->reason;
            }
        };

        $this->assertNull($reason(base64_encode('<a/>')));
        $this->assertSame('bad_encoding', $reason(null));
        $this->assertSame('bad_encoding', $reason(''));
        $this->assertSame('bad_encoding', $reason('***'));
        $this->assertSame('too_large', $reason(str_repeat('A', 262145)));
        $this->assertSame('doctype', $reason(base64_encode('<!DOCTYPE a><a/>')));
        $this->assertSame('doctype', $reason(base64_encode('<a><!doctype x></a>')));
        $this->assertSame('doctype', $reason(base64_encode('<!ENTITY x "y"><a/>')));
        $this->assertNull($reason(base64_encode(gzdeflate('<a/>')), true));
        $this->assertSame('doctype', $reason(base64_encode(gzdeflate('<!DOCTYPE a><a/>')), true));
    }

    public function testADeflateBombIsCutOff(): void
    {
        $bomb = base64_encode(gzdeflate(str_repeat('A', 5 * 1024 * 1024)));

        $this->assertLessThan(262144, strlen($bomb));
        $this->expectExceptionObject(new SamlException('bad_encoding'));
        SamlXml::decode($bomb, true);
    }

    public function testCertificateParsingGivesFingerprintAndRefusesWeakOrBrokenCertificates(): void
    {
        $pair = FakeSamlIdp::pair('idp');
        $cert = IdpMetadata::certificate($pair['cert']);

        $this->assertSame(hash('sha256', (string) base64_decode(FakeSamlIdp::certBody($pair['cert']))), $cert['fingerprint']);
        $this->assertSame($cert['fingerprint'], SamlXml::fingerprint("-----BEGIN CERTIFICATE-----\n" . chunk_split(FakeSamlIdp::certBody($pair['cert']), 64) . "-----END CERTIFICATE-----"));
        $this->assertNotNull($cert['not_after']);

        foreach (['', 'garbage', 'AAAA'] as $bad) {
            try {
                IdpMetadata::certificate($bad);
                $this->fail("accepted {$bad}");
            } catch (SamlException $e) {
                $this->assertSame('certificate_invalid', $e->reason);
            }
        }
        $this->expectExceptionObject(new SamlException('certificate_weak'));
        IdpMetadata::certificate(FakeSamlIdp::pair('weak', 1024)['cert']);
    }
}
