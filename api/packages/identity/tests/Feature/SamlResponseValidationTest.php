<?php

namespace Ulams\Identity\Tests\Feature;

use DOMDocument;
use DOMElement;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Ulams\Auth\Models\User;
use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Models\IdentitySession;
use Ulams\Identity\Models\SamlReplay;
use Ulams\Identity\Tests\Support\FakeSamlIdp;
use Ulams\Identity\Tests\Support\RunsOidcLogin;
use Ulams\Identity\Tests\Support\RunsSamlLogin;
use Ulams\Identity\Tests\TestCase;

/**
 * Every rule of the assertion consumer service (plan 4.3, 4.14): each fixture is a response a real attack or a real
 * misconfiguration produces, and each must end in a refusal with a fixed reason code, no account and no session.
 */
class SamlResponseValidationTest extends TestCase
{
    use RunsOidcLogin;
    use RunsSamlLogin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->samlProvider();
        $this->newVerifier();
    }

    /** Signs in with a response that must be refused, and checks that nothing was granted. */
    private function assertRefused(array $overrides, string|array $reasons, array $options = []): void
    {
        $users = User::query()->count();
        $result = $this->samlSignIn($overrides, $options);
        $params = $this->returned($result['response']);

        $this->assertSame('provider_failed', $params['error'] ?? null, 'The browser must be sent back with an error');
        $this->assertArrayNotHasKey('code', $params);
        $this->assertContains($this->lastFailure(), (array) $reasons);
        $this->assertSame($users, User::query()->count(), 'No account may be created');
        $this->assertSame(0, IdentitySession::query()->count());
        $this->assertSame(0, IdentityAudit::query()->where('action', 'sso.login')->count());
    }

    private function otherKey(): array
    {
        return FakeSamlIdp::pair('attacker');
    }

    // --- signatures ---------------------------------------------------------------------------------------------

    public function testAnUnsignedResponseIsRefused(): void
    {
        $this->assertRefused(['sign' => 'none'], 'unsigned');
    }

    public function testASignedResponseWithAnUnsignedAssertionIsRefused(): void
    {
        // the identity is in the assertion: signing only the envelope proves nothing about it
        $this->assertRefused(['sign' => 'response'], 'unsigned');
    }

    public function testResponseAndAssertionBothSignedIsAccepted(): void
    {
        $params = $this->returned($this->samlSignIn(['sign' => 'both'])['response']);

        $this->assertArrayHasKey('code', $params);
    }

    public function testAResponseSignedByAnotherKeyIsRefused(): void
    {
        $this->assertRefused(['key' => $this->otherKey()], 'signature_invalid');
    }

    public function testATamperedAssertionIsRefused(): void
    {
        $mutate = function (DOMDocument $dom): void {
            $nameId = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'NameID')->item(0);
            $nameId->nodeValue = 'admin-1';
        };

        $this->assertRefused(['mutate' => $mutate], ['signature_invalid', 'invalid_response']);
        $this->assertSame(0, User::query()->where('email', 'ada@example.com')->count());
    }

    public function testATamperedSignatureValueIsRefused(): void
    {
        $mutate = function (DOMDocument $dom): void {
            $value = $dom->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'SignatureValue')->item(0);
            $bytes = base64_decode($value->nodeValue);
            $bytes[10] = chr(ord($bytes[10]) ^ 0x01);
            $value->nodeValue = base64_encode($bytes);
        };

        $this->assertRefused(['mutate' => $mutate], 'signature_invalid');
    }

    public function testACertificateEmbeddedInTheResponseIsNotTrusted(): void
    {
        // signed by the attacker, with the attacker's certificate in KeyInfo: only the stored certificates count
        $this->assertRefused(['key' => $this->otherKey()], 'signature_invalid');
    }

    // --- XML signature wrapping ---------------------------------------------------------------------------------

    /** @return \Closure(DOMDocument):void a second, unsigned assertion for another person in front of the signed one */
    private function duplicateAssertion(): \Closure
    {
        return function (DOMDocument $dom): void {
            $assertion = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Assertion')->item(0);
            $evil = $assertion->cloneNode(true);
            foreach (iterator_to_array($evil->childNodes) as $child) {
                if ($child instanceof DOMElement && $child->localName === 'Signature') {
                    $evil->removeChild($child);
                }
            }
            $evil->setAttribute('ID', '_evil');
            $evil->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'NameID')->item(0)->nodeValue = 'admin-1';
            $assertion->parentNode->insertBefore($evil, $assertion);
        };
    }

    public function testASecondUnsignedAssertionInFrontOfTheSignedOneIsRefused(): void
    {
        $this->assertRefused(['mutate' => $this->duplicateAssertion()], 'assertion_count');
    }

    public function testTheSignedAssertionHiddenInAnotherAssertionsAdviceIsRefused(): void
    {
        $mutate = function (DOMDocument $dom): void {
            $ns = 'urn:oasis:names:tc:SAML:2.0:assertion';
            $signed = $dom->getElementsByTagNameNS($ns, 'Assertion')->item(0);
            $evil = $dom->createElementNS($ns, 'saml:Assertion');
            $evil->setAttribute('ID', '_evil');
            $evil->setAttribute('Version', '2.0');
            $evil->setAttribute('IssueInstant', $signed->getAttribute('IssueInstant'));
            $signed->parentNode->replaceChild($evil, $signed);
            $advice = $dom->createElementNS($ns, 'saml:Advice');
            $advice->appendChild($signed);
            $evil->appendChild($advice);
        };

        $this->assertRefused(['mutate' => $mutate], ['assertion_count', 'signature_structure', 'unsigned', 'signature_invalid', 'invalid_response']);
    }

    public function testTheSignedAssertionInExtensionsWithAnUnsignedOneInItsPlaceIsRefused(): void
    {
        $mutate = function (DOMDocument $dom): void {
            $samlp = 'urn:oasis:names:tc:SAML:2.0:protocol';
            $signed = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Assertion')->item(0);
            $evil = $signed->cloneNode(true);
            foreach (iterator_to_array($evil->childNodes) as $child) {
                if ($child instanceof DOMElement && $child->localName === 'Signature') {
                    $evil->removeChild($child);
                }
            }
            $evil->setAttribute('ID', '_evil');
            $evil->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'NameID')->item(0)->nodeValue = 'admin-1';
            $extensions = $dom->createElementNS($samlp, 'samlp:Extensions');
            $signed->parentNode->replaceChild($evil, $signed);
            $extensions->appendChild($signed);
            $dom->documentElement->insertBefore($extensions, $evil);
        };

        $this->assertRefused(['mutate' => $mutate], ['assertion_count', 'signature_structure', 'unsigned', 'signature_invalid', 'invalid_response', 'invalid_xml']);
    }

    public function testASignatureMovedOutOfItsElementIsRefused(): void
    {
        $mutate = function (DOMDocument $dom): void {
            $signature = $dom->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature')->item(0);
            $status = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:protocol', 'Status')->item(0);
            $status->appendChild($signature);
        };

        $this->assertRefused(['mutate' => $mutate], ['signature_structure', 'unsigned', 'invalid_xml', 'signature_invalid']);
    }

    public function testASignatureThatReferencesAnotherElementIsRefused(): void
    {
        $mutate = function (DOMDocument $dom): void {
            $reference = $dom->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Reference')->item(0);
            $reference->setAttribute('URI', '#_something-else');
        };

        $this->assertRefused(['mutate' => $mutate], ['signature_structure', 'signature_invalid', 'invalid_response']);
    }

    public function testADuplicatedIdIsRefused(): void
    {
        $mutate = function (DOMDocument $dom): void {
            $dom->documentElement->setAttribute('ID', $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Assertion')->item(0)->getAttribute('ID'));
        };

        $this->assertRefused(['mutate' => $mutate], ['signature_structure', 'signature_invalid', 'invalid_response', 'invalid_xml']);
    }

    public function testAnotherElementWithTheIdOfTheSignedAssertionIsRefused(): void
    {
        $mutate = function (DOMDocument $dom): void {
            $assertion = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Assertion')->item(0);
            $extensions = $dom->createElementNS('urn:oasis:names:tc:SAML:2.0:protocol', 'samlp:Extensions');
            $extensions->appendChild($decoy = $dom->createElementNS('urn:example:decoy', 'x:decoy'));
            $decoy->setAttribute('ID', $assertion->getAttribute('ID'));
            $dom->documentElement->insertBefore($extensions, $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:protocol', 'Status')->item(0));
        };

        // the signature itself is untouched (the extensions are outside the signed assertion): xmlseclibs refuses to resolve
        // a reference that two elements answer to, and our own check of unique ids is the second line
        $this->assertRefused(['mutate' => $mutate], ['signature_structure', 'signature_invalid']);
    }

    public function testACommentInsideTheNameIdIsRefused(): void
    {
        // a parser that keeps only the text before the comment would read "ada@example.com": the comment is signed away
        $this->assertRefused(['name_id' => 'ada@example.com', 'name_id_comment' => 'x'], 'comment_in_value');
    }

    // --- algorithms ---------------------------------------------------------------------------------------------

    public function testSha1SignaturesAreRefusedByDefault(): void
    {
        $this->assertRefused(['sig_alg' => XMLSecurityKey::RSA_SHA1, 'digest_alg' => XMLSecurityDSig::SHA1], 'sha1_not_allowed');
    }

    public function testASha1SignatureWithASha256DigestIsStillSha1(): void
    {
        $this->assertRefused(['sig_alg' => XMLSecurityKey::RSA_SHA1], 'sha1_not_allowed');
    }

    public function testASha1DigestAloneIsRefused(): void
    {
        $this->assertRefused(['digest_alg' => XMLSecurityDSig::SHA1], 'sha1_not_allowed');
    }

    public function testSha1IsAcceptedForAProviderThatOptedInAndEveryUseIsAudited(): void
    {
        IdentityProviderRow::enableSha1();

        $result = $this->samlSignIn(['sig_alg' => XMLSecurityKey::RSA_SHA1, 'digest_alg' => XMLSecurityDSig::SHA1]);

        $this->assertArrayHasKey('code', $this->returned($result['response']));
        $row = IdentityAudit::query()->where('action', 'saml.sha1_accepted')->firstOrFail();
        $this->assertSame('acme-saml', $row->data['slug']);
    }

    public function testStrongerSignaturesAreAccepted(): void
    {
        foreach ([[XMLSecurityKey::RSA_SHA384, 'http://www.w3.org/2001/04/xmldsig-more#sha384'], [XMLSecurityKey::RSA_SHA512, XMLSecurityDSig::SHA512]] as [$sig, $digest]) {
            $this->newVerifier();
            $result = $this->samlSignIn(['sig_alg' => $sig, 'digest_alg' => $digest]);
            $this->assertArrayHasKey('code', $this->returned($result['response']), $sig);
            $this->assertSame(0, IdentityAudit::query()->where('action', 'saml.sha1_accepted')->count());
        }
    }

    public function testAnAlgorithmOutsideTheAllowListIsRefused(): void
    {
        $mutate = function (DOMDocument $dom): void {
            $dom->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'SignatureMethod')->item(0)->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#hmac-sha1');
        };

        $this->assertRefused(['mutate' => $mutate], 'algorithm_not_allowed');
    }

    public function testAnXsltTransformIsRefused(): void
    {
        $mutate = function (DOMDocument $dom): void {
            $transforms = $dom->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Transforms')->item(0);
            $transform = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Transform');
            $transform->setAttribute('Algorithm', 'http://www.w3.org/TR/1999/REC-xslt-19991116');
            $transforms->appendChild($transform);
        };

        $this->assertRefused(['mutate' => $mutate], 'algorithm_not_allowed');
    }

    // --- request binding, audience, destination ------------------------------------------------------------------

    public function testAResponseForAnotherAudienceIsRefused(): void
    {
        $this->assertRefused(['audience' => 'https://another-sp.example.test/metadata'], 'audience_mismatch');
    }

    public function testAnAudiencePrefixOrSuffixIsNotTheAudience(): void
    {
        $sp = $this->spEntityId();
        $this->assertRefused(['audience' => $sp . '/evil'], 'audience_mismatch');
        $this->newVerifier();
        $this->assertRefused(['audience' => substr($sp, 0, -1)], 'audience_mismatch');
    }

    public function testAResponseWithoutAnAudienceRestrictionIsRefused(): void
    {
        $mutate = function (DOMDocument $dom): void {
            $conditions = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Conditions')->item(0);
            while ($conditions->firstChild) {
                $conditions->removeChild($conditions->firstChild);
            }
        };

        // the mutation changes signed content, so sign again over the mutated assertion
        $this->assertRefused(['audience' => [], 'mutate' => $mutate], ['audience_mismatch', 'signature_invalid', 'invalid_xml', 'invalid_response']);
    }

    public function testEveryAudienceRestrictionMustNameThisServiceProvider(): void
    {
        // two restrictions are ANDed: one that does not name us fails the assertion
        $this->assertRefused(['audience' => [$this->spEntityId(), 'https://another-sp.example.test/metadata']], 'audience_mismatch');
    }

    public function testSeveralAudiencesInOneRestrictionAreAlternatives(): void
    {
        $result = $this->samlSignIn(['audience' => [['https://another-sp.example.test/metadata', $this->spEntityId()]]]);

        $this->assertArrayHasKey('code', $this->returned($result['response']));
    }

    public function testADestinationThatIsNotTheAcsUrlIsRefused(): void
    {
        $this->assertRefused(['destination' => 'http://localhost/api/identity/saml/other/acs'], 'destination_mismatch');
        $this->newVerifier();
        $this->assertRefused(['destination' => 'http://localhost'], 'destination_mismatch');
        $this->newVerifier();
        $this->assertRefused(['destination' => null], 'destination_mismatch');
        $this->newVerifier();
        $this->assertRefused(['destination' => $this->acsUrl() . '/extra'], 'destination_mismatch');
    }

    public function testARecipientThatIsNotTheAcsUrlIsRefused(): void
    {
        $this->assertRefused(['recipient' => 'http://localhost/api/identity/saml/other/acs'], ['recipient_mismatch', 'subject_confirmation']);
        $this->newVerifier();
        $this->assertRefused(['recipient' => 'http://evil.test/?http://localhost/api/identity/saml/acme-saml/acs'], ['recipient_mismatch', 'subject_confirmation']);
        $this->newVerifier();
        $this->assertRefused(['recipient' => null], ['recipient_mismatch', 'subject_confirmation']);
    }

    public function testAResponseToAnotherRequestIsRefused(): void
    {
        $this->assertRefused(['response_in_response_to' => '_another-request', 'in_response_to' => '_another-request'], 'in_response_to_mismatch');
    }

    public function testAnUnsolicitedResponseIsRefused(): void
    {
        $this->assertRefused(['response_in_response_to' => false, 'in_response_to' => null], 'in_response_to_mismatch');
    }

    public function testASubjectConfirmationForAnotherRequestIsRefused(): void
    {
        $this->assertRefused(['in_response_to' => '_another-request'], ['subject_confirmation', 'recipient_mismatch', 'in_response_to_mismatch']);
    }

    public function testAnIssuerThatIsNotTheIdentityProviderIsRefused(): void
    {
        $this->assertRefused(['issuer' => 'https://evil.example.test/idp'], 'issuer_mismatch');
    }

    public function testAFailureStatusIsRefused(): void
    {
        $this->assertRefused(['status' => 'urn:oasis:names:tc:SAML:2.0:status:Responder'], 'status_not_success');
    }

    // --- time ---------------------------------------------------------------------------------------------------

    public function testAnExpiredAssertionIsRefused(): void
    {
        $this->assertRefused(['not_on_or_after' => time() - 600, 'scd_not_on_or_after' => time() - 600], 'expired');
    }

    public function testASubjectConfirmationThatExpiredIsRefused(): void
    {
        $this->assertRefused(['scd_not_on_or_after' => time() - 600], ['subject_confirmation', 'recipient_mismatch', 'expired']);
    }

    public function testAnAssertionThatIsNotValidYetIsRefused(): void
    {
        $this->assertRefused(['not_before' => time() + 900], 'not_yet_valid');
    }

    public function testAClockDifferenceWithinTheSkewIsAccepted(): void
    {
        $result = $this->samlSignIn(['not_before' => time() + 90, 'not_on_or_after' => time() - 60, 'scd_not_on_or_after' => time() - 60, 'issued' => time() + 30]);

        $this->assertArrayHasKey('code', $this->returned($result['response']));
    }

    public function testAClockDifferenceBeyondTheSkewIsRefused(): void
    {
        $this->assertRefused(['not_on_or_after' => time() - 240, 'scd_not_on_or_after' => time() - 240], 'expired');
    }

    public function testAnAssertionIssuedLongAgoIsRefusedEvenWhenItsWindowIsOpen(): void
    {
        $this->assertRefused(['issued' => time() - 3600, 'not_on_or_after' => time() + 300, 'scd_not_on_or_after' => time() + 300], 'assertion_too_old');
    }

    public function testAnAssertionIssuedInTheFutureIsRefused(): void
    {
        $this->assertRefused(['issued' => time() + 3600], ['not_yet_valid']);
    }

    public function testASubjectConfirmationWithoutAnExpiryIsRefused(): void
    {
        $this->assertRefused(['scd_not_on_or_after' => null], ['subject_confirmation', 'recipient_mismatch']);
    }

    // --- replay -------------------------------------------------------------------------------------------------

    public function testTheSameResponseCannotBePostedTwice(): void
    {
        $first = $this->samlSignIn();
        $this->assertArrayHasKey('code', $this->returned($first['response']));

        // the login state is burned: nothing trusted says where to send the browser
        $second = $this->postAcs($first['saml'], $first['authn']['relay']);

        $second->assertStatus(400);
        $this->assertSame(1, IdentityAudit::query()->where('action', 'sso.login')->count());
    }

    public function testTheSameAssertionIdIsAcceptedOnce(): void
    {
        $first = $this->samlSignIn(['assertion_id' => '_assertion-fixed']);
        $this->assertArrayHasKey('code', $this->returned($first['response']));
        $this->assertSame(1, SamlReplay::query()->count());

        // a new login, a new request id, and an assertion with an id that was accepted already
        $this->newVerifier();
        $users = User::query()->count();
        $second = $this->samlSignIn(['assertion_id' => '_assertion-fixed']);

        $params = $this->returned($second['response']);
        $this->assertSame('provider_failed', $params['error'] ?? null);
        $this->assertSame('replayed', $this->lastFailure());
        $this->assertSame($users, User::query()->count());
    }

    public function testRefusedResponsesAreNotRecordedAsUsed(): void
    {
        $this->assertRefused(['assertion_id' => '_assertion-bad', 'sign' => 'none'], 'unsigned');
        $this->assertSame(0, SamlReplay::query()->count());
    }

    // --- input hygiene -------------------------------------------------------------------------------------------

    public function testADoctypeIsRefusedBeforeParsing(): void
    {
        $raw = fn (string $xml) => preg_replace('/^(<\?xml[^>]*\?>)?/', '$1<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>', $xml, 1);

        $this->assertRefused(['raw' => $raw], 'doctype');
    }

    public function testAnEntityDeclarationAnywhereIsRefused(): void
    {
        $raw = fn (string $xml) => str_replace('<samlp:Status>', '<!ENTITY a "b"><samlp:Status>', $xml);

        $this->assertRefused(['raw' => $raw], 'doctype');
    }

    public function testAnOversizedBodyIsRefused(): void
    {
        $this->assertRefused([], 'too_large', ['response' => base64_encode(str_repeat('A', 300000))]);
    }

    public function testAResponseThatIsNotBase64IsRefused(): void
    {
        $this->assertRefused([], 'bad_encoding', ['response' => '%%% not base64 %%%']);
    }

    public function testAResponseThatIsNotXmlIsRefused(): void
    {
        $this->assertRefused([], 'invalid_xml', ['response' => base64_encode('this is not xml')]);
    }

    public function testAMissingResponseIsRefused(): void
    {
        $authn = $this->authnRequestOf($this->start('acme-saml'));

        $params = $this->returned($this->postAcs(null, $authn['relay']));

        $this->assertSame('provider_failed', $params['error']);
        $this->assertSame('bad_encoding', $this->lastFailure());
    }

    public function testAnAcsPostWithoutAStateIsRefusedWithoutARedirect(): void
    {
        // an IdP-initiated response: no RelayState, no request to answer
        $authn = ['id' => '_unused'];
        $saml = $this->responseFor($authn, ['response_in_response_to' => false, 'in_response_to' => null]);

        $this->postAcs($saml, null)->assertStatus(400);
        $this->postAcs($saml, 'not-a-state')->assertStatus(400);
        $this->postAcs($saml, str_repeat('a', 43) . '.9999999999.' . str_repeat('b', 43))->assertStatus(400);
        $this->assertSame(0, User::query()->count());
    }

    // --- encrypted assertions -----------------------------------------------------------------------------------

    public function testAnEncryptedAssertionIsDecryptedAndAccepted(): void
    {
        $result = $this->samlSignIn(['encrypt_for' => FakeSamlIdp::pair('sp-0')['cert']]);

        $this->assertArrayHasKey('code', $this->returned($result['response']));
        $this->assertSame(1, User::query()->where('email', 'ada@example.com')->count());
    }

    public function testEncryptedAssertionsAreRequiredWhenTheProviderSaysSo(): void
    {
        IdentityProviderRow::update(['saml' => ['want_encrypted_assertions' => true]]);

        $this->assertRefused([], 'encryption_required');
        $this->newVerifier();
        $result = $this->samlSignIn(['encrypt_for' => FakeSamlIdp::pair('sp-0')['cert']]);
        $this->assertArrayHasKey('code', $this->returned($result['response']));
    }

    public function testAnAssertionEncryptedForAnotherKeyIsRefused(): void
    {
        $this->assertRefused(['encrypt_for' => FakeSamlIdp::pair('attacker')['cert']], 'decryption_failed');
    }

    public function testRsa15KeyTransportIsRefused(): void
    {
        $this->assertRefused(['encrypt_for' => FakeSamlIdp::pair('sp-0')['cert'], 'key_transport' => XMLSecurityKey::RSA_1_5], 'algorithm_not_allowed');
    }

    public function testAnAssertionEncryptedToThePreparedNextKeyIsAccepted(): void
    {
        $next = FakeSamlIdp::pair('sp-1');
        IdentityProviderRow::forceFill(['saml_sp_next_key' => $next['key'], 'saml_sp_next_cert' => $next['cert']]);

        $result = $this->samlSignIn(['encrypt_for' => $next['cert']]);

        $this->assertArrayHasKey('code', $this->returned($result['response']));
    }

    // --- subject and account rules -------------------------------------------------------------------------------

    public function testATransientNameIdIsRefusedWithoutAnAttributeThatCarriesTheIdentity(): void
    {
        $this->assertRefused(['name_id_format' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient'], 'transient_name_id');
    }

    public function testATransientNameIdWorksWhenAnAttributeNamesTheSubject(): void
    {
        IdentityProviderRow::update(['saml' => ['attribute_map' => ['subject' => 'employeeNumber']]]);

        $result = $this->samlSignIn(['name_id_format' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient', 'attributes' => ['email' => 'ada@example.com', 'employeeNumber' => 'E-77']]);

        $this->assertArrayHasKey('code', $this->returned($result['response']));
        $this->assertSame('E-77', \Ulams\Identity\Models\IdentityLink::query()->firstOrFail()->subject);
    }

    public function testTheEmailAddressCanComeFromTheNameId(): void
    {
        $result = $this->samlSignIn(['name_id' => 'grace@example.com', 'name_id_format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress', 'attributes' => ['givenName' => ['Grace']]]);

        $this->assertArrayHasKey('code', $this->returned($result['response']));
        $this->assertSame(1, User::query()->where('email', 'grace@example.com')->count());
    }

    public function testNamedAttributesOfTheProviderAreUsed(): void
    {
        IdentityProviderRow::update(['saml' => ['attribute_map' => ['email' => 'urn:custom:mail', 'first_name' => 'urn:custom:first', 'last_name' => 'urn:custom:last']]]);

        $result = $this->samlSignIn(['attributes' => ['urn:custom:mail' => 'linus@example.com', 'urn:custom:first' => 'Linus', 'urn:custom:last' => 'Torvalds']]);

        $this->assertArrayHasKey('code', $this->returned($result['response']));
        $user = User::query()->where('email', 'linus@example.com')->firstOrFail();
        $this->assertSame('Torvalds', $user->last_name);
    }

    public function testWithoutAnEmailAddressNobodyIsCreated(): void
    {
        $result = $this->samlSignIn(['attributes' => ['givenName' => ['Ada']]]);

        $this->assertSame('email_required', $this->returned($result['response'])['error']);
        $this->assertSame(0, User::query()->where('first_name', 'Ada')->count());
    }

    public function testAProviderThatIsNotTrustedForEmailDoesNotVouchForAddresses(): void
    {
        IdentityProviderRow::forceFill(['trust_email' => false]);

        $result = $this->samlSignIn();

        $this->assertSame('email_unverified', $this->returned($result['response'])['error']);
        $this->assertSame(0, User::query()->where('email', 'ada@example.com')->count());
    }

    public function testAnAdminAccountIsNeverLinkedByEmail(): void
    {
        $admin = $this->admin(['email' => 'ada@example.com']);

        $result = $this->samlSignIn();

        $this->assertSame('link_required', $this->returned($result['response'])['error']);
        $this->assertSame(0, \Ulams\Identity\Models\IdentityLink::query()->where('user_id', $admin->id)->count());
    }

    public function testAnExistingStudentIsLinkedWhenTheProviderIsTrustedForTheDomain(): void
    {
        $student = $this->student(['email' => 'ada@example.com', 'email_verified_at' => now()]);

        $result = $this->samlSignIn();

        $this->assertArrayHasKey('code', $this->returned($result['response']));
        $this->assertSame($student->id, \Ulams\Identity\Models\IdentityLink::query()->firstOrFail()->user_id);
    }

    public function testASecondSignInOfTheSamePersonCreatesNobody(): void
    {
        $this->assertArrayHasKey('code', $this->returned($this->samlSignIn()['response']));
        $this->newVerifier();
        $this->assertArrayHasKey('code', $this->returned($this->samlSignIn()['response']));

        $this->assertSame(1, User::query()->where('email', 'ada@example.com')->count());
        $this->assertSame(2, IdentityAudit::query()->where('action', 'sso.login')->count());
    }

    public function testNothingOfTheMessageIsInTheAuditTrail(): void
    {
        $this->assertRefused(['sign' => 'none', 'attributes' => ['email' => 'ada@example.com', 'secret' => 'hunter2-hunter2']], 'unsigned');

        $trail = json_encode(IdentityAudit::query()->get()->toArray());
        $this->assertStringNotContainsString('hunter2', $trail);
        $this->assertStringNotContainsString('SAMLResponse', $trail);
    }
}

/** Small helpers to change the provider row the tests started with. */
final class IdentityProviderRow
{
    public static function enableSha1(): void
    {
        self::update(['saml' => ['allow_sha1' => true]]);
    }

    /** @param array<string,mixed> $changes `saml` is merged into the stored settings */
    public static function update(array $changes): void
    {
        $provider = \Ulams\Identity\Models\IdentityProvider::query()->firstOrFail();
        if (isset($changes['saml'])) {
            $changes['saml'] = array_replace((array) $provider->saml, $changes['saml']);
        }
        $provider->forceFill($changes)->save();
    }

    public static function forceFill(array $changes): void
    {
        self::update($changes);
    }
}
