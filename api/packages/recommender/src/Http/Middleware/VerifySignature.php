<?php

namespace EscolaLms\Recommender\Http\Middleware;

use Closure;
use EscolaLms\Recommender\EscolaLmsRecommenderServiceProvider;
use Illuminate\Http\Request;

class VerifySignature
{
    public function handle(Request $request, Closure $next)
    {
        $rawBody = $request->getContent();
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');

        $secret = config(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.signature_secret');

        if (!$timestamp || !$signature) {
            return response()->json(['message' => 'Missing signature headers'], 401);
        }

        $expected = hash_hmac('sha256', $rawBody . $timestamp, $secret);

        if (!hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        return $next($request);
    }
}
