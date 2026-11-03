<?php

namespace Ulams\Sdk;

use Psr\Http\Message\RequestInterface;

/** Guzzle middleware: `Idempotency-Key` on requests that change data, `X-Request-Id` on all of them. */
final class RequestIds
{
    private const MUTATING = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public static function middleware(): callable
    {
        return static fn (callable $handler) => static function (RequestInterface $request, array $options) use ($handler) {
            if (!$request->hasHeader('X-Request-Id')) {
                $request = $request->withHeader('X-Request-Id', self::uuid());
            }
            if (in_array($request->getMethod(), self::MUTATING, true) && !$request->hasHeader('Idempotency-Key')) {
                $request = $request->withHeader('Idempotency-Key', self::uuid());
            }

            return $handler($request, $options);
        };
    }

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
