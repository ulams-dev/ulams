<?php

namespace Ulams\Jitsi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates calls to the Jitsi/JaaS webhook.
 *
 * Accepted when either:
 * - the X-Jaas-Signature header ("t=<unix time>,v1=<signature>") carries an HMAC-SHA256 of "<t>.<raw body>" made with
 *   jitsi.webhook_secret (base64 or hex encoded), within jitsi.webhook_tolerance seconds; or
 * - the Authorization header is "Bearer <jitsi.webhook_token>".
 *
 * With neither secret configured every request is rejected.
 */
class VerifyJitsiWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('jitsi.webhook_secret', '');
        $token = (string) config('jitsi.webhook_token', '');

        $authenticated = ($secret !== '' && self::isValidSignature(
            (string) $request->getContent(),
            $request->header('X-Jaas-Signature'),
            $secret,
            (int) config('jitsi.webhook_tolerance', 300)
        )) || ($token !== '' && is_string($request->bearerToken()) && hash_equals($token, $request->bearerToken()));

        if (!$authenticated) {
            Log::warning('Jitsi webhook rejected: missing or invalid authentication', ['ip' => $request->ip()]);

            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated webhook'),
            ], $secret === '' && $token === '' ? 403 : 401);
        }

        return $next($request);
    }

    public static function isValidSignature(string $payload, ?string $header, string $secret, int $tolerance = 300, ?int $now = null): bool
    {
        if (empty($header) || $secret === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't' && is_numeric($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && is_string($value) && $value !== '') {
                $signatures[] = $value;
            }
        }

        if (is_null($timestamp) || empty($signatures)) {
            return false;
        }

        // Accept timestamps in seconds or milliseconds.
        $seconds = $timestamp > 100000000000 ? intdiv($timestamp, 1000) : $timestamp;
        if ($tolerance > 0 && abs(($now ?? time()) - $seconds) > $tolerance) {
            return false;
        }

        $raw = hash_hmac('sha256', $timestamp . '.' . $payload, $secret, true);
        $expected = [base64_encode($raw), bin2hex($raw)];

        foreach ($signatures as $signature) {
            foreach ($expected as $candidate) {
                if (hash_equals($candidate, $signature)) {
                    return true;
                }
            }
        }

        return false;
    }
}
