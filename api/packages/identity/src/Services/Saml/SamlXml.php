<?php

namespace Ulams\Identity\Services\Saml;

use DOMDocument;
use DOMXPath;
use OneLogin\Saml2\Utils;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Ulams\Identity\Exceptions\SamlException;

/**
 * Checks on raw SAML XML that run before and next to the library's own validation: size, encoding, DOCTYPE, the
 * allow-lists of signature, digest, transform and encryption algorithms (plan 4.3), and comments inside values.
 */
final class SamlXml
{
    public const NS = [
        'samlp' => 'urn:oasis:names:tc:SAML:2.0:protocol',
        'saml' => 'urn:oasis:names:tc:SAML:2.0:assertion',
        'ds' => 'http://www.w3.org/2000/09/xmldsig#',
        'xenc' => 'http://www.w3.org/2001/04/xmlenc#',
        'md' => 'urn:oasis:names:tc:SAML:2.0:metadata',
    ];

    public const SIGNATURE_ALGORITHMS = [
        XMLSecurityKey::RSA_SHA256, XMLSecurityKey::RSA_SHA384, XMLSecurityKey::RSA_SHA512,
    ];
    public const SHA1_SIGNATURE = XMLSecurityKey::RSA_SHA1;

    public const DIGEST_ALGORITHMS = [
        XMLSecurityDSig::SHA256, XMLSecurityDSig::SHA384, XMLSecurityDSig::SHA512,
    ];
    public const SHA1_DIGEST = XMLSecurityDSig::SHA1;

    /** Enveloped signature plus the two exclusive canonicalisations (and the inclusive one some IdPs use). */
    public const TRANSFORMS = [
        'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
        'http://www.w3.org/2001/10/xml-exc-c14n#',
        'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
    ];
    public const CANONICALIZATIONS = [
        'http://www.w3.org/2001/10/xml-exc-c14n#',
        'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
    ];

    /** RSA 1.5 key transport is Bleichenbacher-prone and 3DES is weak: never accepted for encrypted assertions. */
    public const KEY_TRANSPORT = [XMLSecurityKey::RSA_OAEP_MGF1P, XMLSecurityKey::RSA_OAEP];
    public const DATA_ENCRYPTION = [
        XMLSecurityKey::AES128_CBC, XMLSecurityKey::AES192_CBC, XMLSecurityKey::AES256_CBC,
        XMLSecurityKey::AES128_GCM, XMLSecurityKey::AES192_GCM, XMLSecurityKey::AES256_GCM,
    ];

    /**
     * Decodes a base64 message (optionally deflated, as in the redirect binding) with a size limit and without
     * a DOCTYPE or entity declaration.
     *
     * @throws SamlException
     */
    public static function decode(?string $encoded, bool $inflate = false): string
    {
        $limit = (int) config('ulams_identity.saml.max_body_bytes');
        if (!is_string($encoded) || $encoded === '') {
            throw new SamlException(SamlException::BAD_ENCODING);
        }
        if (strlen($encoded) > $limit) {
            throw new SamlException(SamlException::TOO_LARGE);
        }
        $xml = base64_decode($encoded, true);
        if ($xml === false || $xml === '') {
            throw new SamlException(SamlException::BAD_ENCODING);
        }
        if ($inflate) {
            // the redirect binding deflates; the output is capped, so a deflate bomb comes back as false
            $inflated = @gzinflate($xml, $limit * 4);
            if ($inflated !== false) {
                $xml = $inflated;
            } elseif (!str_starts_with(ltrim($xml), '<')) {
                throw new SamlException(SamlException::BAD_ENCODING);
            }
        }
        if (strlen($xml) > $limit * 4) {
            throw new SamlException(SamlException::TOO_LARGE);
        }
        self::assertNoDoctype($xml);

        return $xml;
    }

    /** @throws SamlException */
    public static function assertNoDoctype(string $xml): void
    {
        // UTF-16 text would hide the marker from a byte search: the library rejects those after parsing
        if (stripos($xml, '<!doctype') !== false || stripos($xml, '<!entity') !== false) {
            throw new SamlException(SamlException::DOCTYPE);
        }
    }

    /** @throws SamlException */
    public static function load(string $xml): DOMDocument
    {
        self::assertNoDoctype($xml);
        try {
            $dom = Utils::loadXML(new DOMDocument(), $xml);
        } catch (\Throwable) {
            throw new SamlException(SamlException::INVALID_XML);
        }
        if (!$dom instanceof DOMDocument || $dom->documentElement === null) {
            throw new SamlException(SamlException::INVALID_XML);
        }

        return $dom;
    }

    public static function xpath(DOMDocument $dom): DOMXPath
    {
        $xpath = new DOMXPath($dom);
        foreach (self::NS as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        return $xpath;
    }

    /**
     * Every signature, digest, canonicalisation and transform algorithm named anywhere in the document must be on
     * the allow-list; SHA-1 only when the provider opted in. Returns whether SHA-1 is in use.
     *
     * @throws SamlException
     */
    public static function assertAlgorithms(DOMDocument $dom, bool $allowSha1): bool
    {
        $xpath = self::xpath($dom);
        $sha1 = false;

        foreach ($xpath->query('//ds:SignatureMethod/@Algorithm') as $attr) {
            $alg = trim($attr->value);
            if ($alg === self::SHA1_SIGNATURE) {
                $sha1 = true;
                continue;
            }
            if (!in_array($alg, self::SIGNATURE_ALGORITHMS, true)) {
                throw new SamlException(SamlException::ALGORITHM_NOT_ALLOWED);
            }
        }
        foreach ($xpath->query('//ds:DigestMethod/@Algorithm') as $attr) {
            $alg = trim($attr->value);
            if ($alg === self::SHA1_DIGEST) {
                $sha1 = true;
                continue;
            }
            if (!in_array($alg, self::DIGEST_ALGORITHMS, true)) {
                throw new SamlException(SamlException::ALGORITHM_NOT_ALLOWED);
            }
        }
        foreach ($xpath->query('//ds:CanonicalizationMethod/@Algorithm') as $attr) {
            if (!in_array(trim($attr->value), self::CANONICALIZATIONS, true)) {
                throw new SamlException(SamlException::ALGORITHM_NOT_ALLOWED);
            }
        }
        foreach ($xpath->query('//ds:Transform/@Algorithm') as $attr) {
            if (!in_array(trim($attr->value), self::TRANSFORMS, true)) {
                throw new SamlException(SamlException::ALGORITHM_NOT_ALLOWED);
            }
        }
        if ($sha1 && !$allowSha1) {
            throw new SamlException(SamlException::SHA1_NOT_ALLOWED);
        }

        return $sha1;
    }

    /**
     * Encrypted assertions: the algorithms that wrap and encrypt (checked on the document as received).
     *
     * @throws SamlException
     */
    public static function assertEncryptionAlgorithms(DOMDocument $dom): void
    {
        $xpath = self::xpath($dom);
        foreach ($xpath->query('//xenc:EncryptedKey/xenc:EncryptionMethod/@Algorithm') as $attr) {
            if (!in_array(trim($attr->value), self::KEY_TRANSPORT, true)) {
                throw new SamlException(SamlException::ALGORITHM_NOT_ALLOWED);
            }
        }
        foreach ($xpath->query('//xenc:EncryptedData/xenc:EncryptionMethod/@Algorithm') as $attr) {
            if (!in_array(trim($attr->value), self::DATA_ENCRYPTION, true)) {
                throw new SamlException(SamlException::ALGORITHM_NOT_ALLOWED);
            }
        }
    }

    /**
     * A comment inside a NameID or attribute value is the "comment injection" trick: a parser that keeps the text
     * before the comment reads another identity than the one that was signed. Refuse such documents outright.
     *
     * @throws SamlException
     */
    public static function assertNoCommentsInValues(DOMDocument $dom): void
    {
        $xpath = self::xpath($dom);
        if ($xpath->query('//saml:NameID//comment() | //saml:AttributeValue//comment() | //saml:Issuer//comment() | //saml:Audience//comment()')->length > 0) {
            throw new SamlException(SamlException::COMMENT_IN_VALUE);
        }
    }

    public static function fingerprint(string $pemOrBody): string
    {
        return strtolower(hash('sha256', (string) base64_decode(self::certBody($pemOrBody), true)));
    }

    /** The base64 body of a certificate without header, footer and whitespace. */
    public static function certBody(string $pem): string
    {
        return (string) preg_replace('/\s+|-----(?:BEGIN|END) CERTIFICATE-----/', '', $pem);
    }
}
