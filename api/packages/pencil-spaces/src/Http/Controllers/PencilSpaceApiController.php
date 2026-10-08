<?php

namespace EscolaLms\PencilSpaces\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\PencilSpaces\Facades\PencilSpace;
use EscolaLms\PencilSpaces\Http\Requests\LoginPencilSpaceRequest;
use Illuminate\Http\JsonResponse;

class PencilSpaceApiController extends EscolaLmsBaseController
{
    public function login(LoginPencilSpaceRequest $request): JsonResponse
    {
        $url = PencilSpace::getDirectLoginUrl($request->getUserId(), $request->getUrl());

        return $this->sendResponse(['url' => $url]);
    }
}
