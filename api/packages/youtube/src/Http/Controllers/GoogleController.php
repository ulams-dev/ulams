<?php

namespace Ulams\Youtube\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Youtube\Http\Requests\GoogleGenerateUrlRequest;
use Ulams\Youtube\Services\Contracts\YoutubeServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GoogleController extends UlamsBaseController
{
    private YoutubeServiceContract $youtubeServiceContract;

    public function __construct(
        YoutubeServiceContract $youtubeServiceContract
    ) {
        $this->youtubeServiceContract = $youtubeServiceContract;
    }

    public function generateUrl(GoogleGenerateUrlRequest $generateUrlRequest): Response
    {
        return response([
            'url' => $this->youtubeServiceContract->generateYTAuthUrl($generateUrlRequest->input('email'))
        ]);
    }

    public function setRefreshToken(Request $request): void
    {
        $this->youtubeServiceContract->setRefreshToken($request->input('code'));
    }
}
