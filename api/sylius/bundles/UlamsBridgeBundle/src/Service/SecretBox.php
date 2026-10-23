<?php

declare(strict_types=1);

namespace Ulams\Bridge\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Seals channel secrets at rest with libsodium; the key is derived from APP_SECRET, so a database dump alone is not enough. */
final class SecretBox
{
    public function __construct(#[Autowire('%env(APP_SECRET)%')] private readonly string $appSecret)
    {
    }

    public function seal(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key()));
    }

    public function open(string $sealed): string
    {
        $raw = base64_decode($sealed, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('The stored secret is malformed.');
        }
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key());
        if ($plain === false) {
            throw new \RuntimeException('The stored secret cannot be opened (was APP_SECRET changed?).');
        }

        return $plain;
    }

    private function key(): string
    {
        return sodium_crypto_generichash('ulams-bridge-secret-box|' . $this->appSecret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
