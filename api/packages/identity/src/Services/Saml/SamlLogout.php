<?php

namespace Ulams\Identity\Services\Saml;

use DOMDocument;
use DOMElement;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\LogoutRequest;
use OneLogin\Saml2\LogoutResponse;
use OneLogin\Saml2\Utils;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Throwable;
use Ulams\Identity\Exceptions\SamlException;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Models\IdentitySession;

/**
 * Single logout (plan 4.6), the two directions:
 *
 * - SP-initiated: {@see initiate()} ends the local session behind a token and builds the signed LogoutRequest the
 *   browser takes to the IdP; the IdP's LogoutResponse comes back to the SLO endpoint and sends the browser to
 *   the allow-listed return URL.
 * - IdP-initiated: a LogoutRequest at the SLO endpoint ends every token of the NameID (and SessionIndexes) and is
 *   answered with a signed LogoutResponse.
 *
 * Every message from the IdP must be signed by one of the provider's certificates (redirect binding signature or
 * an enveloped XML signature), name the IdP as issuer, be addressed to our SLO URL and be fresh; a LogoutRequest is
 * accepted once.
 */
class SamlLogout
{
    public function __construct(private readonly SamlService $saml)
    {
    }

    /**
     * Ends the IdP session's local side and returns the URL of the LogoutRequest for the IdP, or null when the
     * token did not come from this provider or the IdP offers no single logout.
     */
    public function initiate(IdentityProvider $provider, SamlContext $ctx, string $tokenId, string $returnTo): ?string
    {
        /** @var IdentitySession|null $session */
        $session = IdentitySession::query()->where('provider_id', $provider->getKey())->where('token_id', $tokenId)->whereNull('ended_at')->first();
        if ($session === null) {
            return null;
        }
        $saml = (array) $provider->saml;
        if (empty($saml['slo_url'])) {
            $session->forceFill(['ended_at' => now()])->save();

            return null;
        }

        $settings = $this->saml->settings($provider, $ctx);
        $auth = new Auth($this->saml->settingsArray($provider, $ctx));
        $request = new LogoutRequest($settings, null, $session->name_id, $session->session_index, $session->name_id_format);
        $encoded = $request->getRequest();
        $parameters = ['SAMLRequest' => $encoded, 'SigAlg' => XMLSecurityKey::RSA_SHA256];
        $parameters['Signature'] = $auth->buildRequestSignature($encoded, null, XMLSecurityKey::RSA_SHA256);
        // the request waits for the IdP's answer in the database: another process handles that request
        $session->forceFill(['ended_at' => now(), 'logout_request_hash' => hash('sha256', $request->id), 'logout_return_to' => $returnTo])->save();

        return SamlService::url($settings->getIdPSLOUrl(), $parameters);
    }

    /**
     * Handles a message at the SLO endpoint.
     *
     * @return array{kind:string,redirect:?string,revoked:int}
     *         kind `request` (IdP-initiated: redirect is the LogoutResponse to send, or null) or `response`
     *         (SP-initiated finished: redirect is the return URL)
     * @throws SamlException
     */
    public function handle(IdentityProvider $provider, SamlContext $ctx, Request $request): array
    {
        $post = $request->isMethod('POST');
        $raw = $post ? [] : $this->rawQuery($request);
        $value = static fn (string $key): ?string => $post
            ? (is_string($request->request->get($key)) ? (string) $request->request->get($key) : null)
            : (isset($raw[$key]) ? urldecode($raw[$key]) : null);

        $hasRequest = $value('SAMLRequest') !== null;
        $hasResponse = $value('SAMLResponse') !== null;
        if ($hasRequest === $hasResponse) {
            throw new SamlException(SamlException::INVALID);
        }
        $this->saml->assertConfigured($provider);

        return $hasRequest
            ? $this->handleRequest($provider, $ctx, $value('SAMLRequest'), $value('RelayState'), $post, $raw)
            : $this->handleResponse($provider, $ctx, $value('SAMLResponse'), $post, $raw);
    }

    /** @param array<string,string> $raw */
    private function handleRequest(IdentityProvider $provider, SamlContext $ctx, ?string $encoded, ?string $relayState, bool $post, array $raw): array
    {
        $dom = SamlXml::load(SamlXml::decode($encoded, !$post));
        $root = $dom->documentElement;
        if ($root->localName !== 'LogoutRequest' || $root->namespaceURI !== SamlXml::NS['samlp']) {
            throw new SamlException(SamlException::INVALID);
        }
        $this->assertSigned($provider, $dom, 'SAMLRequest', $post, $raw);
        $this->assertEnvelope($provider, $ctx, $dom, true);

        $id = trim($root->getAttribute('ID'));
        $this->saml->remember($provider, 'logout:' . $id, $this->expiry($dom));

        $settings = $this->saml->settings($provider, $ctx);
        try {
            $nameId = trim((string) LogoutRequest::getNameId($dom, (string) $provider->saml_sp_key));
            $indexes = array_values(array_filter(array_map('strval', (array) LogoutRequest::getSessionIndexes($dom)), 'strlen'));
        } catch (Throwable) {
            throw new SamlException(SamlException::NO_NAME_ID);
        }
        if ($nameId === '') {
            throw new SamlException(SamlException::NO_NAME_ID);
        }

        $revoked = $this->revoke($provider, $nameId, $indexes);

        $saml = (array) $provider->saml;
        $target = (string) ($saml['slo_response_url'] ?? $saml['slo_url'] ?? '');
        $redirect = null;
        if ($target !== '') {
            $builder = new LogoutResponse($settings);
            $builder->build($id);
            $encodedResponse = $builder->getResponse();
            $relay = $relayState !== null && strlen($relayState) <= 512 ? $relayState : null;
            $parameters = ['SAMLResponse' => $encodedResponse];
            if ($relay !== null) {
                $parameters['RelayState'] = $relay;
            }
            $parameters['SigAlg'] = XMLSecurityKey::RSA_SHA256;
            $auth = new Auth($this->saml->settingsArray($provider, $ctx));
            $parameters['Signature'] = $auth->buildResponseSignature($encodedResponse, $relay, XMLSecurityKey::RSA_SHA256);
            $redirect = SamlService::url($target, $parameters);
        }

        return ['kind' => 'request', 'redirect' => $redirect, 'revoked' => $revoked];
    }

    /** @param array<string,string> $raw */
    private function handleResponse(IdentityProvider $provider, SamlContext $ctx, ?string $encoded, bool $post, array $raw): array
    {
        $dom = SamlXml::load(SamlXml::decode($encoded, !$post));
        $root = $dom->documentElement;
        if ($root->localName !== 'LogoutResponse' || $root->namespaceURI !== SamlXml::NS['samlp']) {
            throw new SamlException(SamlException::INVALID);
        }
        $this->assertSigned($provider, $dom, 'SAMLResponse', $post, $raw);
        $this->assertEnvelope($provider, $ctx, $dom, false);

        $inResponseTo = trim($root->getAttribute('InResponseTo'));
        $hash = hash('sha256', $inResponseTo);
        /** @var IdentitySession|null $session */
        $session = $inResponseTo === ''
            ? null
            : IdentitySession::query()->where('provider_id', $provider->getKey())->where('logout_request_hash', $hash)
                ->where('ended_at', '>=', now()->subSeconds((int) config('ulams_identity.saml.slo_ttl')))->first();
        // an answer counts once: only the request that clears the hash wins
        $won = $session === null ? 0 : IdentitySession::query()->whereKey($session->getKey())->where('logout_request_hash', $hash)->update(['logout_request_hash' => null]);
        if ($won !== 1 || !is_string($session->logout_return_to) || $session->logout_return_to === '') {
            throw new SamlException(SamlException::IN_RESPONSE_TO);
        }

        return ['kind' => 'response', 'redirect' => $session->logout_return_to, 'revoked' => 0];
    }

    /**
     * The signature of a message from the IdP: redirect binding (signature over the raw query) or an enveloped XML
     * signature (POST binding), made with one of the provider's certificates and an allowed algorithm.
     *
     * @param array<string,string> $raw
     * @throws SamlException
     */
    private function assertSigned(IdentityProvider $provider, DOMDocument $dom, string $type, bool $post, array $raw): void
    {
        $allowSha1 = (bool) (((array) $provider->saml)['allow_sha1'] ?? false);
        $certs = array_map(fn (array $c) => Utils::formatCert((string) $c['pem'], true), (array) $provider->saml_idp_certs);

        if ($post) {
            $this->assertEnvelopeSignature($dom, $certs, $allowSha1);

            return;
        }

        $encodedAlg = $raw['SigAlg'] ?? null;
        $encodedSignature = $raw['Signature'] ?? null;
        if ($encodedAlg === null || $encodedSignature === null) {
            throw new SamlException(SamlException::UNSIGNED);
        }
        $alg = urldecode($encodedAlg);
        if ($alg === SamlXml::SHA1_SIGNATURE) {
            if (!$allowSha1) {
                throw new SamlException(SamlException::SHA1_NOT_ALLOWED);
            }
        } elseif (!in_array($alg, SamlXml::SIGNATURE_ALGORITHMS, true)) {
            throw new SamlException(SamlException::ALGORITHM_NOT_ALLOWED);
        }
        $signature = base64_decode(urldecode($encodedSignature), true);
        if ($signature === false || !isset($raw[$type])) {
            throw new SamlException(SamlException::SIGNATURE_INVALID);
        }
        // the signed string is made of the values exactly as they were sent (still percent-encoded)
        $message = $type . '=' . $raw[$type] . (isset($raw['RelayState']) ? '&RelayState=' . $raw['RelayState'] : '') . '&SigAlg=' . $encodedAlg;
        foreach ($certs as $pem) {
            try {
                $key = new XMLSecurityKey($alg, ['type' => 'public']);
                $key->loadKey($pem, false, true);
                if ($key->verifySignature($message, $signature) === 1) {
                    return;
                }
            } catch (Throwable) {
                continue;
            }
        }
        throw new SamlException(SamlException::SIGNATURE_INVALID);
    }

    /**
     * @param list<string> $certs PEM
     * @throws SamlException
     */
    private function assertEnvelopeSignature(DOMDocument $dom, array $certs, bool $allowSha1): void
    {
        SamlXml::assertAlgorithms($dom, $allowSha1);
        $xp = SamlXml::xpath($dom);
        $root = $dom->documentElement;
        $ids = [];
        foreach ($xp->query('//@ID') as $attr) {
            $ids[] = $attr->value;
        }
        if (count($ids) !== count(array_unique($ids))) {
            throw new SamlException(SamlException::SIGNATURE_STRUCTURE);
        }
        $signatures = $xp->query('//ds:Signature');
        if ($signatures->length === 0) {
            throw new SamlException(SamlException::UNSIGNED);
        }
        $direct = $xp->query('ds:Signature', $root);
        $reference = $xp->query('ds:SignedInfo/ds:Reference', $direct->item(0) ?? $root);
        if ($signatures->length !== 1 || $direct->length !== 1 || $reference->length !== 1
            || !in_array($reference->item(0)->getAttribute('URI'), ['', '#' . $root->getAttribute('ID')], true)) {
            throw new SamlException(SamlException::SIGNATURE_STRUCTURE);
        }
        try {
            $valid = Utils::validateSign($dom, null, null, 'sha1', '/samlp:' . $root->localName . '/ds:Signature', $certs);
        } catch (Throwable) {
            throw new SamlException(SamlException::SIGNATURE_INVALID);
        }
        if (!$valid) {
            throw new SamlException(SamlException::SIGNATURE_INVALID);
        }
    }

    /**
     * Issuer, Destination and freshness of a LogoutRequest or LogoutResponse.
     *
     * @throws SamlException
     */
    private function assertEnvelope(IdentityProvider $provider, SamlContext $ctx, DOMDocument $dom, bool $isRequest): void
    {
        SamlXml::assertNoCommentsInValues($dom);
        $xp = SamlXml::xpath($dom);
        $root = $dom->documentElement;
        $skew = (int) config('ulams_identity.saml.clock_skew');
        $maxAge = (int) config('ulams_identity.saml.max_age');
        $now = time();

        $issuer = $xp->query('saml:Issuer', $root);
        if ($issuer->length !== 1 || trim($issuer->item(0)->textContent) !== (string) (((array) $provider->saml)['entity_id'] ?? '')) {
            throw new SamlException(SamlException::ISSUER);
        }
        if (trim($root->getAttribute('Destination')) !== $ctx->sloUrl($provider->slug)) {
            throw new SamlException(SamlException::DESTINATION);
        }
        if (trim($root->getAttribute('ID')) === '') {
            throw new SamlException(SamlException::INVALID);
        }
        try {
            $issued = Utils::parseSAML2Time($root->getAttribute('IssueInstant'));
            if ($isRequest && $root->hasAttribute('NotOnOrAfter') && Utils::parseSAML2Time($root->getAttribute('NotOnOrAfter')) + $skew <= $now) {
                throw new SamlException(SamlException::EXPIRED);
            }
        } catch (SamlException $e) {
            throw $e;
        } catch (Throwable) {
            throw new SamlException(SamlException::INVALID);
        }
        if ($issued > $now + $skew) {
            throw new SamlException(SamlException::NOT_YET_VALID);
        }
        if ($issued + $maxAge + $skew < $now) {
            throw new SamlException(SamlException::TOO_OLD);
        }
        if (!$isRequest) {
            $status = $xp->query('samlp:Status/samlp:StatusCode', $root)->item(0);
            if (!$status instanceof DOMElement || $status->getAttribute('Value') !== Constants::STATUS_SUCCESS) {
                throw new SamlException(SamlException::NOT_SUCCESS);
            }
        }
    }

    private function expiry(DOMDocument $dom): int
    {
        $issued = (int) Utils::parseSAML2Time($dom->documentElement->getAttribute('IssueInstant'));

        return $issued + (int) config('ulams_identity.saml.max_age') + (int) config('ulams_identity.saml.clock_skew');
    }

    /** Revokes the tokens of the sessions of a NameID (all of them, or those of the SessionIndexes) and ends them. */
    private function revoke(IdentityProvider $provider, string $nameId, array $sessionIndexes): int
    {
        $query = IdentitySession::query()->where('provider_id', $provider->getKey())->where('name_id', $nameId)->whereNull('ended_at');
        if ($sessionIndexes !== []) {
            $query->whereIn('session_index', $sessionIndexes);
        }
        $sessions = $query->get();
        if ($sessions->isEmpty()) {
            return 0;
        }
        Passport::token()->newQuery()->whereIn('id', $sessions->pluck('token_id')->all())->update(['revoked' => true]);
        IdentitySession::query()->whereIn('id', $sessions->pluck('id')->all())->update(['ended_at' => now()]);

        return $sessions->count();
    }

    /**
     * The query string as it was sent: keys decoded, values left percent-encoded, because the redirect binding's
     * signature is over those exact bytes. The first occurrence of a key wins.
     *
     * @return array<string,string>
     */
    private function rawQuery(Request $request): array
    {
        $out = [];
        foreach (explode('&', (string) $request->server->get('QUERY_STRING', '')) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $out[urldecode($key)] ??= $value;
        }

        return $out;
    }

}
