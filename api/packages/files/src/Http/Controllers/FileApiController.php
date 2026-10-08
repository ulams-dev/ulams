<?php

namespace EscolaLms\Files\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Files\Http\Controllers\Swagger\FileApiSwagger;
use EscolaLms\Files\Http\Requests\FileDeleteRequest;
use EscolaLms\Files\Http\Requests\FileFindByNameRequest;
use EscolaLms\Files\Http\Requests\FileListingRequest;
use EscolaLms\Files\Http\Requests\FileMoveRequest;
use EscolaLms\Files\Http\Requests\FileUploadRequest;
use EscolaLms\Files\Http\Services\Contracts\FileServiceContract;
use Illuminate\Http\JsonResponse;

class FileApiController extends EscolaLmsBaseController implements FileApiSwagger
{
    private FileServiceContract $service;

    /**
     * @param FileServiceContract $files
     */
    public function __construct(FileServiceContract $files)
    {
        $this->service = $files;
    }

    /**
     * @param FileUploadRequest $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function upload(FileUploadRequest $request): JsonResponse
    {
        $target = $request->get('target');
        $files = $request->file('file');

        $list = $this->service->findAll($target, $files);
        if (!empty($list)) {
            $this->sendError(sprintf("Following files already exist: %s", join(", ", $list)), 409);
        }

        $paths = $this->service->putAll($target, $files);

        return $this->sendResponse($paths, 'files uploaded successfully');
    }

    public function list(FileListingRequest $request): JsonResponse
    {
        $perPage = $request->getPerPage();
        $page = $request->getPage();

        $list = $this->service->listInfo($request->getDirectory());
        $info = [
            'current_page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($list->count() / $perPage),
            'total' => $list->count(),
            'data' => $list->forPage($page, $perPage)->values()
        ];

        return $this->sendResponse($info, 'file list fetched successfully');
    }

    public function move(FileMoveRequest $request): JsonResponse
    {
        $success = $this->service->move($request->getParamSource(), $request->getParamDestination());

        return $this->sendResponse($success, 'file moved successfully');
    }

    public function delete(FileDeleteRequest $request): JsonResponse
    {
        $success = $this->service->delete($request->getParamUrl());

        return $this->sendResponse($success, 'file deleted successfully');
    }

    /**
     * @param FileFindByNameRequest $request
     * @return JsonResponse
     */
    public function findByName(FileFindByNameRequest $request): JsonResponse
    {
        $perPage = $request->getPerPage();
        $page = $request->getPage();

        $list = $this->service->findByName($request->getDirectory(), $request->getName());
        $info = [
            'current_page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($list->count() / $perPage),
            'total' => $list->count(),
            'data' => $list->forPage($page, $perPage)->values()
        ];

        return $this->sendResponse($info, 'file list fetched successfully');
    }
}
