<?php

namespace Ulams\PencilSpaces\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\PencilSpaces\Facades\PencilSpace;
use Ulams\PencilSpaces\Http\Requests\LoginPencilSpaceRequest;
use Illuminate\Http\JsonResponse;

class PencilSpaceApiController extends UlamsBaseController
{
    public function login(LoginPencilSpaceRequest $request): JsonResponse
    {
        $url = PencilSpace::getDirectLoginUrl($request->getUserId(), $request->getUrl());

        return $this->sendResponse(['url' => $url]);
    }
}
