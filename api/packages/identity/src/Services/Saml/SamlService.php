<?php

namespace Ulams\Identity\Services\Saml;

use DOMElement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\AuthnRequest;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\Response;
use OneLogin\Saml2\Settings;
use OneLogin\Saml2\Utils;
use OneLogin\Saml2\ValidationError;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Throwable;
use Ulams\Identity\Exceptions\SamlException;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Models\SamlReplay;

/**
 * The SAML 2.0 service provider of one identity provider (plan 4.3), on onelogin/php-saml configured per request
 * from the provider row (no settings file). The library does the XML signature work; this class adds the rules the
 * library leaves loose and refuses everything else:
 *
 * - signature on the assertion required; signature, digest, canonicalisation and transform algorithms on an
 *   allow-list (SHA-1 only for a provider that opted in); only the certificates stored for this provider;
 * - the response answers the AuthnRequest this browser started (InResponseTo), arrives at our ACS URL
 *   (Destination and Recipient exactly), is meant for this SP (every Audience restriction), comes from the
 *   IdP's entity id, is fresh (IssueInstant, NotBefore, NotOnOrAfter with the clock skew) and is accepted once;
 * - no DOCTYPE, bodies limited in size, no comments inside identities, one assertion, XSW structure checks of
 *   the library, optional and required encrypted assertions with OAEP key transport only.
 */
class SamlService
{
    /**
     * @return array{entity_id:string,acs_url:string,slo_url:string,metadata_url:string}
     */
    public function spUrls(IdentityProvider $provider, SamlContext $ctx): array
    {
        return [
            'entity_id' => $ctx->metadataUrl($provider->slug),
            'metadata_url' => $ctx->metadataUrl($provider->slug),
            'acs_url' => $ctx->acsUrl($provider->slug),
            'slo_url' => $ctx->sloUrl($provider->slug),
        ];
    }

    /** The settings array of onelogin/php-saml for this provider on this host. */
    public function settingsArray(IdentityProvider $provider, SamlContext $ctx, bool $nextKey = false, bool $spOnly = false): array
    {
        $saml = (array) $provider->saml;
        $urls = $this->spUrls($provider, $ctx);
        $cert = (string) ($nextKey ? $provider->saml_sp_next_cert : $provider->saml_sp_cert);
        $key = (string) ($nextKey ? $provider->saml_sp_next_key : $provider->saml_sp_key);

        $sp = [
            'entityId' => $urls['entity_id'],
            'assertionConsumerService' => ['url' => $urls['acs_url'], 'binding' => Constants::BINDING_HTTP_POST],
            'singleLogoutService' => ['url' => $urls['slo_url'], 'binding' => Constants::BINDING_HTTP_REDIRECT],
            'NameIDFormat' => (string) ($saml['name_id_format'] ?? Constants::NAMEID_UNSPECIFIED) ?: Constants::NAMEID_UNSPECIFIED,
            'x509cert' => $cert,
            'privateKey' => $key,
        ];
        if (!$nextKey && (string) $provider->saml_sp_next_cert !== '') {
            $sp['x509certNew'] = (string) $provider->saml_sp_next_cert;
        }

        $settings = [
            'strict' => true,
            'debug' => false,
            'sp' => $sp,
            'compress' => ['requests' => true, 'responses' => true],
            'security' => [
                'nameIdEncrypted' => false,
                'authnRequestsSigned' => true,
                'logoutRequestSigned' => true,
                'logoutResponseSigned' => true,
                'signMetadata' => false,
                'wantMessagesSigned' => false,
                // the assertion carries the identity: its signature is not optional
                'wantAssertionsSigned' => true,
                'wantNameId' => true,
                'wantNameIdEncrypted' => false,
                'wantAssertionsEncrypted' => (bool) ($saml['want_encrypted_assertions'] ?? false),
                'wantXMLValidation' => true,
                'relaxDestinationValidation' => false,
                'destinationStrictlyMatches' => true,
                'allowRepeatAttributeName' => false,
                'rejectUnsolicitedResponsesWithInResponseTo' => true,
                'requestedAuthnContext' => false,
                'signatureAlgorithm' => XMLSecurityKey::RSA_SHA256,
                'digestAlgorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256',
                'lowercaseUrlencoding' => false,
            ],
        ];

        if (!$spOnly) {
            $idp = [
                'entityId' => (string) ($saml['entity_id'] ?? ''),
                'singleSignOnService' => ['url' => (string) ($saml['sso_url'] ?? ''), 'binding' => Constants::BINDING_HTTP_REDIRECT],
                'x509certMulti' => ['signing' => array_values(array_map(fn (array $c) => (string) $c['pem'], (array) $provider->saml_idp_certs))],
            ];
            if (!empty($saml['slo_url'])) {
                $idp['singleLogoutService'] = ['url' => (string) $saml['slo_url'], 'binding' => Constants::BINDING_HTTP_REDIRECT];
                if (!empty($saml['slo_response_url'])) {
                    $idp['singleLogoutService']['responseUrl'] = (string) $saml['slo_response_url'];
                }
            }
            $settings['idp'] = $idp;
        }

        return $settings;
    }

    /** @throws SamlException */
    public function settings(IdentityProvider $provider, SamlContext $ctx, bool $nextKey = false, bool $spOnly = false): Settings
    {
        if (!$spOnly) {
            $this->assertConfigured($provider);
        }
        try {
            return new Settings($this->settingsArray($provider, $ctx, $nextKey, $spOnly), $spOnly);
        } catch (Throwable) {
            throw new SamlException(SamlException::MISCONFIGURED);
        }
    }

    /** @throws SamlException */
    public function assertConfigured(IdentityProvider $provider): void
    {
        $saml = (array) $provider->saml;
        if ($provider->protocol !== IdentityProvider::SAML || (string) ($saml['entity_id'] ?? '') === '' || (string) ($saml['sso_url'] ?? '') === ''
            || (array) $provider->saml_idp_certs === [] || (string) $provider->saml_sp_key === '' || (string) $provider->saml_sp_cert === '') {
            throw new SamlException(SamlException::MISCONFIGURED);
        }
    }

    /**
     * The SP metadata an administrator gives the IdP: entity id, ACS and SLO endpoints, the current certificate and,
     * when one is prepared, the next one.
     *
     * @throws SamlException
     */
    public function metadataXml(IdentityProvider $provider, SamlContext $ctx): string
    {
        $settings = $this->settings($provider, $ctx, false, true);
        // the certificate is published for signing only, so an IdP does not start encrypting by itself (its default
        // OAEP digest may be one this stack cannot open): it is also published for encryption when the provider wants it
        $xml = $settings->getSPMetadata(false, null, null, true);
        $errors = $settings->validateMetadata($xml);
        if ($errors !== []) {
            throw new SamlException(SamlException::MISCONFIGURED);
        }

        return $xml;
    }

    /**
     * Prepares an AuthnRequest. The request id is needed before the login state exists (the state stores it), the
     * redirect needs the state as RelayState: hence the two steps.
     *
     * @return array{id:string,redirect:\Closure(string):string}
     * @throws SamlException
     */
    public function beginLogin(IdentityProvider $provider, SamlContext $ctx): array
    {
        $settings = $this->settings($provider, $ctx);
        $auth = new Auth($this->settingsArray($provider, $ctx));
        $request = new AuthnRequest($settings, false, false, true);

        return [
            'id' => $request->getId(),
            'redirect' => function (string $relayState) use ($provider, $settings, $auth, $request): string {
                $parameters = ['SAMLRequest' => $request->getRequest(), 'RelayState' => $relayState];
                $parameters['SigAlg'] = XMLSecurityKey::RSA_SHA256;
                $parameters['Signature'] = $auth->buildRequestSignature($parameters['SAMLRequest'], $relayState, XMLSecurityKey::RSA_SHA256);

                return self::url($settings->getIdPSSOUrl(), $parameters);
            },
        ];
    }

    /**
     * Validates the response the browser posted to the ACS.
     *
     * @throws SamlException with a fixed reason code
     */
    public function validateResponse(IdentityProvider $provider, SamlContext $ctx, ?string $encoded, string $requestId): SamlAssertion
    {
        $xml = SamlXml::decode($encoded);
        $this->assertConfigured($provider);
        $saml = (array) $provider->saml;
        $allowSha1 = (bool) ($saml['allow_sha1'] ?? false);
        $acsUrl = $ctx->acsUrl($provider->slug);
        $entityId = $ctx->metadataUrl($provider->slug);

        $received = SamlXml::load($xml);
        SamlXml::assertEncryptionAlgorithms($received);
        $response = $this->openResponse($provider, $ctx, (string) $encoded, $received);
        $dom = $response->getXMLDocument();

        $usedSha1 = SamlXml::assertAlgorithms($dom, $allowSha1);
        SamlXml::assertNoCommentsInValues($dom);

        $valid = $ctx->run($ctx->path($provider->slug, 'acs'), fn () => $response->isValid($requestId));
        if (!$valid) {
            throw new SamlException(self::reasonFor($response->getErrorException()));
        }

        try {
            $this->assertStrict($dom, $provider, $acsUrl, $entityId, $requestId);
            $nameId = trim((string) $response->getNameId());
            $assertionId = trim((string) $response->getAssertionId());
            $attributes = [];
            foreach ((array) $response->getAttributes() as $name => $values) {
                $attributes[(string) $name] = array_values(array_map('strval', (array) $values));
            }
            $issued = $this->issueInstant($dom);
            $notOnOrAfter = $response->getAssertionNotOnOrAfter();
            $sessionIndex = $response->getSessionIndex();
            $format = $response->getNameIdFormat();
        } catch (ValidationError) {
            throw new SamlException(SamlException::INVALID);
        }
        if ($nameId === '' || strlen($nameId) > 512) {
            throw new SamlException(SamlException::NO_NAME_ID);
        }
        if ($assertionId === '') {
            throw new SamlException(SamlException::INVALID);
        }

        $maxAge = (int) config('ulams_identity.saml.max_age');
        $skew = (int) config('ulams_identity.saml.clock_skew');
        $expires = min($issued + $maxAge, $notOnOrAfter !== null ? (int) $notOnOrAfter : PHP_INT_MAX) + $skew;
        $this->remember($provider, 'assertion:' . $assertionId, $expires);

        return new SamlAssertion($nameId, $format ?: null, $sessionIndex !== null && $sessionIndex !== '' ? (string) $sessionIndex : null, $attributes, $assertionId, $expires, $usedSha1);
    }

    /** Records an id as used; a second use is a replay. */
    public function remember(IdentityProvider $provider, string $id, int $expiresAt): void
    {
        try {
            // a savepoint when a transaction is open: a unique violation must not poison the caller's transaction
            DB::transaction(fn () => SamlReplay::query()->create([
                'provider_id' => $provider->getKey(),
                'id_hash' => hash('sha256', $id),
                'expires_at' => date('Y-m-d H:i:s', max($expiresAt, time() + 60)),
            ]));
        } catch (UniqueConstraintViolationException) {
            throw new SamlException(SamlException::REPLAYED);
        }
    }

    /** Deletes replay records that can no longer matter. */
    public function pruneReplays(): int
    {
        return SamlReplay::query()->where('expires_at', '<', date('Y-m-d H:i:s', time() - 3600))->delete();
    }

    /**
     * @param array<string,string> $parameters
     */
    public static function url(string $endpoint, array $parameters): string
    {
        return $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . http_build_query($parameters, '', '&', PHP_QUERY_RFC1738);
    }

    /** Opens the response with the SP key; an assertion encrypted to the prepared next key opens with that one. */
    private function openResponse(IdentityProvider $provider, SamlContext $ctx, string $encoded, \DOMDocument $received): Response
    {
        $keys = [false];
        if ((string) $provider->saml_sp_next_key !== '' && (string) $provider->saml_sp_next_cert !== '') {
            $keys[] = true;
        }
        $encrypted = $received->getElementsByTagNameNS(SamlXml::NS['saml'], 'EncryptedAssertion')->length > 0;
        $last = null;
        foreach ($keys as $next) {
            try {
                return new Response($this->settings($provider, $ctx, $next), $encoded);
            } catch (SamlException $e) {
                throw $e;
            } catch (Throwable $e) {
                $last = $e;
                if (!$encrypted) {
                    break;
                }
            }
        }
        throw new SamlException($encrypted ? SamlException::DECRYPTION_FAILED : SamlException::INVALID_XML);
    }

    /**
     * What the library accepts loosely: Destination and Recipient must be our ACS URL exactly (the library compares
     * prefixes and substrings), Audience must be present and name this SP in every restriction, the assertion must
     * come from the IdP and be fresh.
     *
     * @throws SamlException
     */
    private function assertStrict(\DOMDocument $dom, IdentityProvider $provider, string $acsUrl, string $entityId, string $requestId): void
    {
        $skew = (int) config('ulams_identity.saml.clock_skew');
        $maxAge = (int) config('ulams_identity.saml.max_age');
        $xp = SamlXml::xpath($dom);
        $root = $dom->documentElement;

        // an element anywhere that reuses the id of the signed one is how a signature gets pointed at the wrong element
        $ids = [];
        foreach ($xp->query('//@ID') as $attribute) {
            $ids[] = $attribute->value;
        }
        if (count($ids) !== count(array_unique($ids))) {
            throw new SamlException(SamlException::SIGNATURE_STRUCTURE);
        }

        if (trim($root->getAttribute('Destination')) !== $acsUrl) {
            throw new SamlException(SamlException::DESTINATION);
        }
        $assertion = $xp->query('/samlp:Response/saml:Assertion')->item(0);
        if (!$assertion instanceof DOMElement || $xp->query('/samlp:Response/saml:Assertion')->length !== 1) {
            throw new SamlException(SamlException::ASSERTION_COUNT);
        }

        $idpEntity = (string) (((array) $provider->saml)['entity_id'] ?? '');
        $issuers = $xp->query('saml:Issuer', $assertion);
        if ($issuers->length !== 1 || trim($issuers->item(0)->textContent) !== $idpEntity) {
            throw new SamlException(SamlException::ISSUER);
        }
        $responseIssuer = $xp->query('saml:Issuer', $root);
        if ($responseIssuer->length > 0 && trim($responseIssuer->item(0)->textContent) !== $idpEntity) {
            throw new SamlException(SamlException::ISSUER);
        }

        $restrictions = $xp->query('saml:Conditions/saml:AudienceRestriction', $assertion);
        if ($restrictions->length === 0) {
            throw new SamlException(SamlException::AUDIENCE);
        }
        foreach ($restrictions as $restriction) {
            $audiences = [];
            foreach ($xp->query('saml:Audience', $restriction) as $audience) {
                $audiences[] = trim($audience->textContent);
            }
            if (!in_array($entityId, $audiences, true)) {
                throw new SamlException(SamlException::AUDIENCE);
            }
        }

        $now = time();
        $confirmed = false;
        foreach ($xp->query('saml:Subject/saml:SubjectConfirmation', $assertion) as $confirmation) {
            if ($confirmation->getAttribute('Method') !== Constants::CM_BEARER) {
                continue;
            }
            $data = $xp->query('saml:SubjectConfirmationData', $confirmation)->item(0);
            if (!$data instanceof DOMElement || $data->getAttribute('Recipient') !== $acsUrl) {
                continue;
            }
            if ($data->hasAttribute('InResponseTo') && $data->getAttribute('InResponseTo') !== $requestId) {
                continue;
            }
            if ($data->hasAttribute('NotBefore') && Utils::parseSAML2Time($data->getAttribute('NotBefore')) > $now + $skew) {
                continue;
            }
            if (!$data->hasAttribute('NotOnOrAfter') || Utils::parseSAML2Time($data->getAttribute('NotOnOrAfter')) + $skew <= $now) {
                continue;
            }
            $confirmed = true;
            break;
        }
        if (!$confirmed) {
            throw new SamlException(SamlException::RECIPIENT);
        }

        $issued = $this->issueInstant($dom);
        if ($issued > $now + $skew) {
            throw new SamlException(SamlException::NOT_YET_VALID);
        }
        if ($issued + $maxAge + $skew < $now) {
            throw new SamlException(SamlException::TOO_OLD);
        }
    }

    private function issueInstant(\DOMDocument $dom): int
    {
        $xp = SamlXml::xpath($dom);
        $assertion = $xp->query('/samlp:Response/saml:Assertion')->item(0);
        if (!$assertion instanceof DOMElement || !$assertion->hasAttribute('IssueInstant')) {
            throw new SamlException(SamlException::INVALID);
        }
        try {
            return (int) Utils::parseSAML2Time($assertion->getAttribute('IssueInstant'));
        } catch (Throwable) {
            throw new SamlException(SamlException::INVALID);
        }
    }

    /** Maps the library's validation error to one of our fixed reason codes. */
    public static function reasonFor(?Throwable $error): string
    {
        if (!$error instanceof ValidationError) {
            // xmlseclibs throws plain exceptions for a digest that does not match, an unknown key and the like
            return $error !== null && preg_match('/reference|signature|digest|canonical|key|certificate/i', $error->getMessage()) ? SamlException::SIGNATURE_INVALID : SamlException::INVALID;
        }

        return match ($error->getCode()) {
            ValidationError::WRONG_NUMBER_OF_ASSERTIONS => SamlException::ASSERTION_COUNT,
            ValidationError::MISSING_STATUS, ValidationError::MISSING_STATUS_CODE, ValidationError::STATUS_CODE_IS_NOT_SUCCESS => SamlException::NOT_SUCCESS,
            ValidationError::WRONG_SIGNED_ELEMENT, ValidationError::ID_NOT_FOUND_IN_SIGNED_ELEMENT, ValidationError::DUPLICATED_ID_IN_SIGNED_ELEMENTS,
            ValidationError::INVALID_SIGNED_ELEMENT, ValidationError::DUPLICATED_REFERENCE_IN_SIGNED_ELEMENTS, ValidationError::UNEXPECTED_SIGNED_ELEMENTS,
            ValidationError::WRONG_NUMBER_OF_SIGNATURES_IN_RESPONSE, ValidationError::WRONG_NUMBER_OF_SIGNATURES_IN_ASSERTION,
            ValidationError::UNEXPECTED_REFERENCE, ValidationError::WRONG_NUMBER_OF_SIGNATURES => SamlException::SIGNATURE_STRUCTURE,
            ValidationError::INVALID_XML_FORMAT => SamlException::INVALID_XML,
            ValidationError::WRONG_INRESPONSETO => SamlException::IN_RESPONSE_TO,
            ValidationError::NO_ENCRYPTED_ASSERTION, ValidationError::NO_ENCRYPTED_NAMEID => SamlException::ENCRYPTION_REQUIRED,
            ValidationError::ASSERTION_TOO_EARLY => SamlException::NOT_YET_VALID,
            ValidationError::ASSERTION_EXPIRED, ValidationError::SESSION_EXPIRED, ValidationError::RESPONSE_EXPIRED => SamlException::EXPIRED,
            ValidationError::WRONG_DESTINATION, ValidationError::EMPTY_DESTINATION => SamlException::DESTINATION,
            ValidationError::WRONG_AUDIENCE => SamlException::AUDIENCE,
            ValidationError::WRONG_ISSUER, ValidationError::ISSUER_MULTIPLE_IN_RESPONSE, ValidationError::ISSUER_NOT_FOUND_IN_ASSERTION => SamlException::ISSUER,
            ValidationError::WRONG_SUBJECTCONFIRMATION => SamlException::SUBJECT,
            ValidationError::NO_SIGNED_MESSAGE, ValidationError::NO_SIGNED_ASSERTION, ValidationError::NO_SIGNATURE_FOUND => SamlException::UNSIGNED,
            ValidationError::INVALID_SIGNATURE => SamlException::SIGNATURE_INVALID,
            ValidationError::NO_NAMEID, ValidationError::EMPTY_NAMEID => SamlException::NO_NAME_ID,
            default => SamlException::INVALID,
        };
    }
}
