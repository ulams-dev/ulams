<?php

declare(strict_types=1);

namespace Ulams\Bridge\Service;

/** The scheme of ADR 0103 / 0130: `Ulams-Signature: t=<unix>,v1=<hex HMAC-SHA256 of "<t>.<raw body>">`. */
final class WebhookSigner
{
    public function sign(string $body, string $secret, ?int $timestamp = null): string
    {
        $t = $timestamp ?? time();

        return 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $body, $secret);
    }
}
