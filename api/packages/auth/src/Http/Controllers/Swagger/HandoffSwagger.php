<?php

namespace Ulams\Auth\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Ulams\Auth\Http\Requests\HandoffExchangeRequest;
use Ulams\Auth\Http\Requests\HandoffIssueRequest;

/**
 * Moving a signed-in learner between the hosts of one academy (ADR 0195): the academy's own front and its entry points.
 */
interface HandoffSwagger
{
    /**
     * @OA\Post(
     *     path="/api/auth/handoff", operationId="auth.handoff.post",
     *     summary="Ask for a one-time code that hands this session to another host of the academy",
     *     description="Needs the caller's own login token (not a scoped token). `host` must be the academy's own front or one of its active entry hosts; `challenge` is the S256 challenge of a verifier that the browser at the target host keeps. The code lives 60 seconds, works once, and is bound to this user, that host and that challenge. The response is never cached. Send the browser to the target host with the code, never the token.",
     *     tags={"Auth"}, security={{"passport": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"host", "challenge"},
     *             @OA\Property(property="host", type="string", example="sales.academy.example.org", description="Host name of the target, without a port"),
     *             @OA\Property(property="challenge", type="string", description="base64url(sha256(verifier)), 43 characters"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="The code",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="code", type="string"),
     *                 @OA\Property(property="expires_in", type="integer", example=60),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(response=403, description="The host is not a front of this academy (or has been disabled)", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
     * )
     */
    public function issue(HandoffIssueRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *     path="/api/auth/handoff/exchange", operationId="auth.handoff.exchange.post",
     *     summary="Exchange a hand-off code for a session token of the target host",
     *     description="Redeemed by the target host's server with the secret `verifier` whose S256 challenge asked for the code and with its own `host`. The code works once, within 60 seconds, and only for the host it was issued for; the host must still be a front of the academy. Returns a new token that belongs to the target host's session. Never call it from a page that may be logged.",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "verifier", "host"},
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="verifier", type="string", description="43 to 128 characters of [A-Za-z0-9._~-]"),
     *             @OA\Property(property="host", type="string", description="Host name of the target, without a port"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token of the target host",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="token", type="string"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time"),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(response=400, description="The code is invalid, expired, already used, for another host, or does not match the verifier", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
     * )
     */
    public function exchange(HandoffExchangeRequest $request): JsonResponse;
}
