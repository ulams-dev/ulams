<?php

namespace Ulams\Lti\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Ulams\Lti\Exceptions\LtiRequestException;

trait RendersLtiResponses
{
    protected function autopost(string $action, array $fields, ?string $message = null): Response
    {
        return response()
            ->view('lti::autopost', ['action' => $action, 'fields' => $fields, 'message' => $message])
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    protected function htmlError(LtiRequestException $e, string $title = 'This activity could not be opened'): Response
    {
        return response()
            ->view('lti::message', ['title' => $title, 'message' => $e->getMessage(), 'error' => true], $e->status)
            ->header('Cache-Control', 'no-store');
    }

    protected function oauthError(LtiRequestException $e): JsonResponse
    {
        return response()->json(['error' => $e->oauthError, 'error_description' => $e->getMessage()], $e->status)
            ->header('Cache-Control', 'no-store');
    }
}
