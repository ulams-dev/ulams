<?php

namespace Ulams\Youtube\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ulams\Auth\Exceptions\LoginHandoffException;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\LoginHandoffService;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Settings\Enums\SettingsPermissionsEnum;
use Ulams\Youtube\Http\Requests\GoogleGenerateUrlRequest;
use Ulams\Youtube\Services\Contracts\YoutubeServiceContract;

class GoogleController extends UlamsBaseController
{
    /** Protocol and provider of the signed state (see {@see LoginHandoffService}). */
    public const STATE_PROTOCOL = 'youtube';
    public const STATE_PROVIDER = 'google';

    public function __construct(
        private readonly YoutubeServiceContract $youtubeServiceContract,
        private readonly LoginHandoffService $handoff
    ) {
    }

    public function generateUrl(GoogleGenerateUrlRequest $generateUrlRequest): Response
    {
        // The state proves to the callback that an administrator with the permission started this
        // flow on this tenant host: signed, bound to the host, single use, valid for ten minutes.
        $state = $this->handoff->startState(
            self::STATE_PROTOCOL,
            self::STATE_PROVIDER,
            $generateUrlRequest->getHost(),
            '',
            hash('sha256', random_bytes(32)),
            ['user_id' => $generateUrlRequest->user()->getKey()]
        );

        return response([
            'url' => $this->youtubeServiceContract->generateYTAuthUrl($generateUrlRequest->input('email'), $state),
        ]);
    }

    /**
     * Google redirects the administrator's browser here, so there is no bearer token; the signed
     * state is the credential. It is checked, and burned, before the code is exchanged.
     */
    public function setRefreshToken(Request $request): JsonResponse
    {
        $code = $request->query('code');
        if (!is_string($code) || $code === '') {
            return $this->refuse('A code is required.');
        }

        try {
            $row = $this->handoff->consumeState(
                $request->query('state'),
                self::STATE_PROTOCOL,
                self::STATE_PROVIDER,
                $request->getHost()
            );
        } catch (LoginHandoffException) {
            return $this->refuse('The state is invalid, expired or already used.');
        }

        // the administrator who started the flow must still be allowed to change the configuration
        $starter = User::query()->find($row->data['user_id'] ?? null);
        if (!$starter || !$starter->can(SettingsPermissionsEnum::CONFIG_UPDATE)) {
            return $this->refuse('The state is invalid, expired or already used.');
        }

        try {
            $this->youtubeServiceContract->setRefreshToken($code);
        } catch (\Throwable $e) {
            report($e);

            return $this->refuse('Google did not accept the code.');
        }

        return response()->json(['success' => true]);
    }

    private function refuse(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], 400);
    }
}
