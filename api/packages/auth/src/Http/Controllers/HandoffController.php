<?php

namespace Ulams\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Ulams\Auth\Exceptions\LoginHandoffException;
use Ulams\Auth\Http\Controllers\Swagger\HandoffSwagger;
use Ulams\Auth\Http\Requests\HandoffExchangeRequest;
use Ulams\Auth\Http\Requests\HandoffIssueRequest;
use Ulams\Auth\Http\Resources\LoginResource;
use Ulams\Auth\Services\LoginHandoffService;
use Ulams\Auth\Services\SessionHandoffService;
use Ulams\Core\Http\Controllers\UlamsBaseController;

class HandoffController extends UlamsBaseController implements HandoffSwagger
{
    public function __construct(private readonly SessionHandoffService $handoff)
    {
    }

    public function issue(HandoffIssueRequest $request): JsonResponse
    {
        try {
            $code = $this->handoff->issue($request->user(), (string) $request->input('host'), (string) $request->input('challenge'));
        } catch (LoginHandoffException $e) {
            // one answer for a host that is not ours, one that was disabled and one that never existed
            return $this->noStore($this->sendError(__('That address does not belong to this academy.'), 403));
        }

        return $this->noStore($this->sendResponse(['code' => $code, 'expires_in' => LoginHandoffService::CODE_TTL_SECONDS], __('Hand-off code')));
    }

    public function exchange(HandoffExchangeRequest $request): JsonResponse
    {
        try {
            $token = $this->handoff->exchange((string) $request->input('code'), (string) $request->input('verifier'), (string) $request->input('host'));
        } catch (LoginHandoffException $e) {
            return $this->noStore($this->sendError(__('The sign-in code is invalid or has expired. Start again.'), 400));
        }

        return $this->noStore($this->sendResponse(LoginResource::make($token)->toArray($request), __('Login successful')));
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
