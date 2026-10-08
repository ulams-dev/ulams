<?php

namespace EscolaLms\Cmi5\Http\Controllers;

use EscolaLms\Cmi5\Http\Controllers\Swagger\Cmi5ControllerSwagger;
use EscolaLms\Cmi5\Http\Requests\Cmi5DeleteRequest;
use EscolaLms\Cmi5\Http\Requests\Cmi5ListRequest;
use EscolaLms\Cmi5\Http\Requests\Cmi5ReadRequest;
use EscolaLms\Cmi5\Http\Requests\Cmi5UploadRequest;
use EscolaLms\Cmi5\Http\Resources\Cmi5Resource;
use EscolaLms\Cmi5\Services\Contracts\Cmi5ServiceContract;
use EscolaLms\Cmi5\Services\Contracts\Cmi5UploadServiceContract;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class Cmi5Controller extends EscolaLmsBaseController implements Cmi5ControllerSwagger
{
    private Cmi5ServiceContract $cmi5Service;
    private Cmi5UploadServiceContract $cmi5UploadService;

    public function __construct(Cmi5ServiceContract $cmi5Service, Cmi5UploadServiceContract $cmi5UploadService)
    {
        $this->cmi5Service = $cmi5Service;
        $this->cmi5UploadService = $cmi5UploadService;
    }

    public function upload(Cmi5UploadRequest $request): JsonResponse
    {
        $cmi5 = $this->cmi5UploadService->upload($request->file('file'));
        return $this->sendResponseForResource(Cmi5Resource::make($cmi5), __('Cmi5 uploaded successfully'));
    }

    public function read(Cmi5ReadRequest $request, int $cmi5AuId): View
    {
        $token = $request->header('Authorization');
        $courseId = $request->get('course_id');
        $topicId = $request->get('topic_id');

        $data = $this->cmi5Service->getPlayerData($cmi5AuId, $token, $courseId, $topicId);

        return view('cmi5::player', ['data' => $data]);
    }

    public function list(Cmi5ListRequest $request): JsonResponse
    {
        $results = $this->cmi5Service->getCmi5s($request->get('per_page'));
        return $this->sendResponseForResource(Cmi5Resource::collection($results), __('Cmi5s retrieved successfully'));
    }

    public function delete(Cmi5DeleteRequest $request): JsonResponse
    {
        $this->cmi5Service->delete($request->getCmi5());
        return $this->sendSuccess(__('Cmi5s deleted successfully'));
    }
}
