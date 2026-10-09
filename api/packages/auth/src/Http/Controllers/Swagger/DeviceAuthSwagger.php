<?php

namespace Ulams\Auth\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Ulams\Auth\Http\Requests\Device\ApproveDeviceRequestRequest;
use Ulams\Auth\Http\Requests\Device\DenyDeviceRequestRequest;
use Ulams\Auth\Http\Requests\Device\DeviceCodeRequest;
use Ulams\Auth\Http\Requests\Device\DeviceTokenRequest;
use Ulams\Auth\Http\Requests\Device\ShowDeviceRequestRequest;

/**
 * @OA\Schema(schema="DeviceCodeResponse", required={"device_code","user_code","verification_uri","verification_uri_complete","expires_in","interval"},
 *     @OA\Property(property="device_code", type="string", description="Secret of the polling client; never shown to the user"),
 *     @OA\Property(property="user_code", type="string", example="BDWP-HQPK"),
 *     @OA\Property(property="verification_uri", type="string", example="https://coffee.app.ulams.app/cli/authorize"),
 *     @OA\Property(property="verification_uri_complete", type="string", example="https://coffee.app.ulams.app/cli/authorize?code=BDWP-HQPK"),
 *     @OA\Property(property="expires_in", type="integer", example=600),
 *     @OA\Property(property="interval", type="integer", example=5))
 *
 * @OA\Schema(schema="DeviceTokenResponse", required={"access_token","token_type","scopes","token_id"},
 *     @OA\Property(property="access_token", type="string", description="ulams_pat_... shown once"),
 *     @OA\Property(property="token_type", type="string", example="Bearer"),
 *     @OA\Property(property="expires_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="scopes", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="token_id", type="string"))
 *
 * @OA\Schema(schema="DeviceError", required={"error"},
 *     @OA\Property(property="error", type="string", enum={"authorization_pending","slow_down","access_denied","expired_token","invalid_scope"}),
 *     @OA\Property(property="error_description", type="string"))
 *
 * @OA\Schema(schema="DeviceRequest",
 *     @OA\Property(property="user_code", type="string"), @OA\Property(property="client_name", type="string"),
 *     @OA\Property(property="agent_name", type="string", nullable=true),
 *     @OA\Property(property="requested_scopes", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="ip", type="string", nullable=true), @OA\Property(property="user_agent", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"), @OA\Property(property="expires_at", type="string", format="date-time"),
 *     @OA\Property(property="expires_in", type="integer"),
 *     @OA\Property(property="expiry_options_days", type="array", @OA\Items(type="integer")),
 *     @OA\Property(property="default_expires_in_days", type="integer"), @OA\Property(property="max_expires_in_days", type="integer"))
 */
interface DeviceAuthSwagger
{
    /**
     * @OA\Post(path="/api/auth/device/code", summary="Start a device login (RFC 8628)", tags={"Auth"},
     *     description="No authentication. Throttled to 10 requests per minute per IP.",
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"client_name","scopes"},
     *         @OA\Property(property="client_name", type="string", example="ulams-cli on mateusz-mbp"),
     *         @OA\Property(property="scopes", type="array", @OA\Items(type="string", example="courses:write")),
     *         @OA\Property(property="agent", type="string", nullable=true))),
     *     @OA\Response(response=200, description="Codes and polling interval", @OA\JsonContent(ref="#/components/schemas/DeviceCodeResponse")),
     *     @OA\Response(response=400, description="invalid_scope", @OA\JsonContent(ref="#/components/schemas/DeviceError")),
     *     @OA\Response(response=422, description="Validation error"), @OA\Response(response=429, description="Too many requests"))
     */
    public function code(DeviceCodeRequest $request): JsonResponse;

    /**
     * @OA\Post(path="/api/auth/device/token", summary="Poll for the token of a device login (RFC 8628)", tags={"Auth"},
     *     description="No authentication. Poll every `interval` seconds. Errors are HTTP 400 with an RFC 8628 error code. The token is returned once.",
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"device_code"},
     *         @OA\Property(property="device_code", type="string"),
     *         @OA\Property(property="grant_type", type="string", example="urn:ietf:params:oauth:grant-type:device_code"))),
     *     @OA\Response(response=200, description="Approved: the scoped token", @OA\JsonContent(ref="#/components/schemas/DeviceTokenResponse")),
     *     @OA\Response(response=400, description="authorization_pending, slow_down, access_denied or expired_token", @OA\JsonContent(ref="#/components/schemas/DeviceError")),
     *     @OA\Response(response=422, description="Validation error"), @OA\Response(response=429, description="Too many requests"))
     */
    public function token(DeviceTokenRequest $request): JsonResponse;

    /**
     * @OA\Get(path="/api/auth/device/requests/{user_code}", summary="Details of a pending device request (for the approval page)", tags={"Auth"}, security={{"passport": {}}},
     *     description="Needs a login token of the approving user (not a scoped token). Throttled to 5 requests per minute per user.",
     *     @OA\Parameter(name="user_code", in="path", required=true, @OA\Schema(type="string", example="BDWP-HQPK")),
     *     @OA\Response(response=200, description="The request", @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", ref="#/components/schemas/DeviceRequest"))),
     *     @OA\Response(response=401, description="Unauthenticated"), @OA\Response(response=403, description="Scoped tokens cannot approve", @OA\JsonContent(ref="#/components/schemas/ScopeError")),
     *     @OA\Response(response=404, description="Unknown, expired or already answered code"), @OA\Response(response=429, description="Too many requests"))
     */
    public function show(ShowDeviceRequestRequest $request): JsonResponse;

    /**
     * @OA\Post(path="/api/auth/device/requests/{user_code}/approve", summary="Approve a device request", tags={"Auth"}, security={{"passport": {}}},
     *     description="Mints a scoped token for the approving user with the approved scopes that the client requested. The CLI collects it on its next poll.",
     *     @OA\Parameter(name="user_code", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"scopes"},
     *         @OA\Property(property="scopes", type="array", @OA\Items(type="string")),
     *         @OA\Property(property="expires_in_days", type="integer", example=90))),
     *     @OA\Response(response=200, description="Approved", @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="object", @OA\Property(property="status", type="string", example="approved"),
     *             @OA\Property(property="scopes", type="array", @OA\Items(type="string")), @OA\Property(property="token_id", type="string")))),
     *     @OA\Response(response=401, description="Unauthenticated"), @OA\Response(response=403, description="Scoped tokens cannot approve"),
     *     @OA\Response(response=404, description="Unknown, expired or already answered code"),
     *     @OA\Response(response=422, description="No requested scope approved, or invalid lifetime"), @OA\Response(response=429, description="Too many requests"))
     */
    public function approve(ApproveDeviceRequestRequest $request): JsonResponse;

    /**
     * @OA\Post(path="/api/auth/device/requests/{user_code}/deny", summary="Deny a device request", tags={"Auth"}, security={{"passport": {}}},
     *     @OA\Parameter(name="user_code", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Denied", @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="object", @OA\Property(property="status", type="string", example="denied")))),
     *     @OA\Response(response=401, description="Unauthenticated"), @OA\Response(response=403, description="Scoped tokens cannot deny"),
     *     @OA\Response(response=404, description="Unknown, expired or already answered code"), @OA\Response(response=429, description="Too many requests"))
     */
    public function deny(DenyDeviceRequestRequest $request): JsonResponse;
}
