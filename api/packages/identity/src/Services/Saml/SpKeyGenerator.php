<?php

namespace Ulams\Identity\Services\Saml;

use RuntimeException;

/**
 * Generates the key pair a provider's service provider side signs requests with and decrypts assertions with
 * (plan 4.2): RSA 3072, a self-signed certificate valid for five years. IdPs pin the certificate from the
 * metadata, not a CA chain, so self-signed is the norm.
 */
class SpKeyGenerator
{
    /** @return array{key:string,cert:string} PEM private key and PEM certificate */
    public function generate(string $commonName): array
    {
        $key = openssl_pkey_new([
            'private_key_bits' => (int) config('ulams_identity.saml.sp_key_bits'),
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($key === false) {
            throw new RuntimeException('Could not generate the SAML service provider key: ' . openssl_error_string());
        }
        $csr = openssl_csr_new(['commonName' => mb_substr($commonName, 0, 64)], $key, ['digest_alg' => 'sha256']);
        $cert = $csr === false ? false : openssl_csr_sign($csr, null, $key, (int) config('ulams_identity.saml.sp_cert_years') * 365, ['digest_alg' => 'sha256']);
        if ($cert === false || !openssl_pkey_export($key, $privateKey) || !openssl_x509_export($cert, $certificate)) {
            throw new RuntimeException('Could not create the SAML service provider certificate: ' . openssl_error_string());
        }

        return ['key' => $privateKey, 'cert' => $certificate];
    }
}
