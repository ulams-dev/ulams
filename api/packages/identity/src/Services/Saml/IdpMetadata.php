<?php

namespace Ulams\Identity\Services\Saml;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\IdPMetadataParser;
use Throwable;
use Ulams\Core\Http\SafeHttp;
use Ulams\Identity\Exceptions\SamlException;

/**
 * Reads an identity provider's SAML metadata: fetched over {@see SafeHttp} (https only, public addresses, size
 * limit) or uploaded, parsed with onelogin/php-saml (which refuses a DOCTYPE), and reduced to what a login needs.
 */
class IdpMetadata
{
    /**
     * @return array{entity_id:string,sso_url:string,slo_url:?string,slo_response_url:?string,name_id_format:?string,certs:list<array{pem:string,fingerprint:string,not_before:?string,not_after:?string}>}
     * @throws SamlException
     */
    public function parse(string $xml, ?string $entityId = null): array
    {
        if (strlen($xml) > (int) config('ulams_identity.saml.metadata_max_bytes')) {
            throw new SamlException(SamlException::METADATA_INVALID);
        }
        SamlXml::assertNoDoctype($xml);
        if ($entityId !== null && str_contains($entityId, '"')) {
            $entityId = null;
        }
        try {
            $info = IdPMetadataParser::parseXML($xml, $entityId, null, Constants::BINDING_HTTP_REDIRECT, Constants::BINDING_HTTP_REDIRECT);
        } catch (Throwable) {
            throw new SamlException(SamlException::METADATA_INVALID);
        }
        $idp = (array) ($info['idp'] ?? []);
        $entity = trim((string) ($idp['entityId'] ?? ''));
        $sso = trim((string) ($idp['singleSignOnService']['url'] ?? ''));
        if ($entity === '' || $sso === '') {
            throw new SamlException(SamlException::METADATA_INVALID);
        }

        $bodies = [];
        foreach ((array) ($idp['x509certMulti']['signing'] ?? []) as $cert) {
            $bodies[] = (string) $cert;
        }
        if (!empty($idp['x509cert'])) {
            $bodies[] = (string) $idp['x509cert'];
        }
        $certs = [];
        foreach ($bodies as $body) {
            $parsed = self::certificate($body);
            $certs[$parsed['fingerprint']] = $parsed;
        }
        if ($certs === []) {
            throw new SamlException(SamlException::METADATA_INVALID);
        }

        return [
            'entity_id' => $entity,
            'sso_url' => $sso,
            'slo_url' => ($idp['singleLogoutService']['url'] ?? '') !== '' ? (string) $idp['singleLogoutService']['url'] : null,
            'slo_response_url' => ($idp['singleLogoutService']['responseUrl'] ?? '') !== '' ? (string) $idp['singleLogoutService']['responseUrl'] : null,
            'name_id_format' => isset($info['sp']['NameIDFormat']) ? (string) $info['sp']['NameIDFormat'] : null,
            'certs' => array_values($certs),
        ];
    }

    /**
     * A certificate the way it is stored: base64 body, SHA-256 fingerprint, validity. Refuses what is not an X.509
     * certificate or carries a weak key (RSA below 2048 bits).
     *
     * @return array{pem:string,fingerprint:string,not_before:?string,not_after:?string}
     * @throws SamlException
     */
    public static function certificate(string $pemOrBody): array
    {
        $body = SamlXml::certBody($pemOrBody);
        $pem = "-----BEGIN CERTIFICATE-----\n" . chunk_split($body, 64, "\n") . "-----END CERTIFICATE-----\n";
        $x509 = base64_decode($body, true) === false || $body === '' ? false : @openssl_x509_read($pem);
        $parsed = $x509 === false ? false : openssl_x509_parse($x509);
        if ($parsed === false) {
            throw new SamlException(SamlException::CERTIFICATE_INVALID);
        }
        $details = openssl_pkey_get_details(openssl_pkey_get_public($x509));
        if (($details['type'] ?? null) === OPENSSL_KEYTYPE_RSA && (int) ($details['bits'] ?? 0) < 2048) {
            throw new SamlException(SamlException::CERTIFICATE_WEAK);
        }

        return [
            'pem' => $body,
            'fingerprint' => SamlXml::fingerprint($body),
            'not_before' => isset($parsed['validFrom_time_t']) ? gmdate('c', (int) $parsed['validFrom_time_t']) : null,
            'not_after' => isset($parsed['validTo_time_t']) ? gmdate('c', (int) $parsed['validTo_time_t']) : null,
        ];
    }

    /** @throws SamlException */
    public function fetch(string $url): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'https' && !($scheme === 'http' && config('ulams_identity.allow_insecure_urls'))) {
            throw new SamlException(SamlException::METADATA_UNREACHABLE);
        }
        try {
            $response = $this->http()->get($url, ['headers' => ['Accept' => 'application/samlmetadata+xml, application/xml, text/xml'], 'http_errors' => false]);
        } catch (GuzzleException|Throwable) {
            throw new SamlException(SamlException::METADATA_UNREACHABLE);
        }
        if ($response->getStatusCode() !== 200) {
            throw new SamlException(SamlException::METADATA_UNREACHABLE);
        }

        return (string) $response->getBody();
    }

    private function http(): Client
    {
        $handler = app()->bound('identity.http_handler') ? app('identity.http_handler') : null;

        return SafeHttp::client($handler, [
            'allow_insecure' => (bool) config('ulams_identity.allow_insecure_urls'),
            'max_redirects' => 3,
            'max_bytes' => (int) config('ulams_identity.saml.metadata_max_bytes'),
            'timeout' => (int) config('ulams_identity.saml.timeout'),
            'label' => 'identity provider metadata URLs',
            'exception' => static fn (string $message): Throwable => new SamlException(SamlException::METADATA_UNREACHABLE),
        ]);
    }
}
