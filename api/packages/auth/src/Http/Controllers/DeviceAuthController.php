<?php

namespace Ulams\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Ulams\Auth\Http\Controllers\Swagger\DeviceAuthSwagger;
use Ulams\Auth\Http\Requests\Device\ApproveDeviceRequestRequest;
use Ulams\Auth\Http\Requests\Device\DenyDeviceRequestRequest;
use Ulams\Auth\Http\Requests\Device\DeviceCodeRequest;
use Ulams\Auth\Http\Requests\Device\DeviceTokenRequest;
use Ulams\Auth\Http\Requests\Device\ShowDeviceRequestRequest;
use Ulams\Auth\Services\Contracts\DeviceAuthorizationServiceContract;
use Ulams\Auth\Services\DeviceAuthorizationService;
use Ulams\Auth\Support\TokenScopes;
use Ulams\Core\Http\Controllers\UlamsBaseController;

/**
 * Device login (ADR 0075). `code` and `token` speak RFC 8628 (flat JSON, errors as HTTP 400
 * `{error}`); the three `requests/{user_code}` endpoints are used by the approval page in the web
 * app with the signed-in user's token and answer in the usual `{success, message, data}` envelope.
 */
class DeviceAuthController extends UlamsBaseController implements DeviceAuthSwagger
{
    public const DEFAULT_EXPIRY_DAYS = 90;

    public const EXPIRY_OPTIONS = [7, 30, 90];

    public function __construct(private DeviceAuthorizationServiceContract $devices)
    {
    }

    public function code(DeviceCodeRequest $request): JsonResponse
    {
        try {
            $started = $this->devices->start(
                $request->input('client_name'),
                $request->input('scopes'),
                $request->input('agent'),
                $request->ip(),
                $request->userAgent(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'invalid_scope', 'error_description' => $e->getMessage()], 400);
        }

        $base = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/') . '/cli/authorize';

        return response()->json([
            'device_code' => $started['device_code'],
            'user_code' => $started['user_code'],
            'verification_uri' => $base,
            'verification_uri_complete' => $base . '?code=' . $started['user_code'],
            'expires_in' => DeviceAuthorizationService::TTL_SECONDS,
            'interval' => DeviceAuthorizationService::INTERVAL_SECONDS,
        ], 200, ['Cache-Control' => 'no-store']);
    }

    public function token(DeviceTokenRequest $request): JsonResponse
    {
        $result = $this->devices->poll($request->input('device_code'));
        $headers = ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache'];
        if (isset($result['error'])) {
            return response()->json($result + ['error_description' => self::DESCRIPTIONS[$result['error']] ?? ''], 400, $headers);
        }

        return response()->json($result, 200, $headers);
    }

    public function show(ShowDeviceRequestRequest $request): JsonResponse
    {
        $a = $request->authorization();

        return $this->sendResponse([
            'user_code' => substr(DeviceAuthorizationService::normalizeUserCode((string) $request->route('user_code')), 0, 4) . '-' . substr(DeviceAuthorizationService::normalizeUserCode((string) $request->route('user_code')), 4),
            'client_name' => $a->client_name,
            'agent_name' => $a->agent_name,
            'requested_scopes' => $a->requested_scopes,
            'ip' => $a->ip,
            'user_agent' => $a->user_agent,
            'created_at' => $a->created_at?->toIso8601String(),
            'expires_at' => $a->expires_at->toIso8601String(),
            'expires_in' => max(0, (int) now()->diffInSeconds($a->expires_at, false)),
            'expiry_options_days' => array_values(array_filter(self::EXPIRY_OPTIONS, fn ($d) => $d <= TokenScopes::maxDays())),
            'default_expires_in_days' => min(self::DEFAULT_EXPIRY_DAYS, TokenScopes::maxDays()),
            'max_expires_in_days' => TokenScopes::maxDays(),
        ], __('Device request'));
    }

    public function approve(ApproveDeviceRequestRequest $request): JsonResponse
    {
        try {
            $row = $this->devices->approve(
                $request->authorization(),
                $request->user(),
                $request->input('scopes'),
                (int) ($request->input('expires_in_days') ?: min(self::DEFAULT_EXPIRY_DAYS, TokenScopes::maxDays())),
            );
        } catch (InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), 422);
        }

        return $this->sendResponse(['status' => $row->status, 'scopes' => $row->approved_scopes, 'token_id' => $row->token_id], __('Approved. The CLI collects its token on the next poll.'));
    }

    public function deny(DenyDeviceRequestRequest $request): JsonResponse
    {
        try {
            $row = $this->devices->deny($request->authorization());
        } catch (InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), 422);
        }

        return $this->sendResponse(['status' => $row->status], __('Denied'));
    }

    private const DESCRIPTIONS = [
        'authorization_pending' => 'The user has not approved the request yet.',
        'slow_down' => 'Poll less often.',
        'access_denied' => 'The user denied the request.',
        'expired_token' => 'The device code expired, was already used or is unknown.',
    ];
}
