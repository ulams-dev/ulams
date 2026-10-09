<?php

namespace Ulams\H5P\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\H5P\Http\Controllers\Swagger\H5PContentAdminApiSwagger;
use Ulams\H5P\Http\Requests\DeleteUnusedH5PContentRequest;
use Ulams\H5P\Http\Requests\ListH5PContentRequest;
use Ulams\H5P\Http\Resources\H5PContentResource;
use Ulams\H5P\Services\Contracts\H5PContentServiceContract;

class H5PContentAdminApiController extends UlamsBaseController implements H5PContentAdminApiSwagger
{
    private H5PContentServiceContract $contentService;

    public function __construct(H5PContentServiceContract $contentService)
    {
        $this->contentService = $contentService;
    }

    public function index(ListH5PContentRequest $request): JsonResponse
    {
        $list = $this->contentService->list(
            $request->getCriteria(),
            $request->getPerPage(),
            $request->getOrderBy(),
            $request->getOrder(),
        );

        return $this->sendResponseForResource(H5PContentResource::collection($list));
    }

    public function deleteUnused(DeleteUnusedH5PContentRequest $request): JsonResponse
    {
        $result = $this->contentService->deleteUnused();

        $message = $result['failed']
            ? sprintf('Deleted %d unused H5P contents, %d failed.', count($result['ids']), count($result['failed']))
            : sprintf('Deleted %d unused H5P contents.', count($result['ids']));

        return $this->sendResponse($result, $message);
    }
}
