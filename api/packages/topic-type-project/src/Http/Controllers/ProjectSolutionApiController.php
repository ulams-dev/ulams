<?php

namespace EscolaLms\TopicTypeProject\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\TopicTypeProject\Http\Controllers\Swagger\ProjectSolutionApiSwagger;
use EscolaLms\TopicTypeProject\Http\Requests\CreateProjectSolutionRequest;
use EscolaLms\TopicTypeProject\Http\Requests\DeleteProjectSolutionRequest;
use EscolaLms\TopicTypeProject\Http\Requests\ListProjectSolutionRequest;
use EscolaLms\TopicTypeProject\Http\Requests\ReadProjectSolutionRequest;
use EscolaLms\TopicTypeProject\Http\Resources\ProjectSolutionResource;
use EscolaLms\TopicTypeProject\Services\Contracts\ProjectSolutionServiceContract;
use Illuminate\Http\JsonResponse;

class ProjectSolutionApiController extends EscolaLmsBaseController implements ProjectSolutionApiSwagger
{
    private ProjectSolutionServiceContract $projectSolutionService;

    public function __construct(ProjectSolutionServiceContract $projectSolutionService)
    {
        $this->projectSolutionService = $projectSolutionService;
    }

    public function index(ListProjectSolutionRequest $request): JsonResponse
    {
        $results = $this->projectSolutionService->findAllByUser($request->getCriteria(), $request->getPage(), auth()->id());

        return $this->sendResponseForResource(ProjectSolutionResource::collection($results));
    }

    public function create(CreateProjectSolutionRequest $request): JsonResponse
    {
        $result = $this->projectSolutionService->create($request->getCreateProjectSolutionDto());

        return $this->sendResponseForResource(ProjectSolutionResource::make($result), __('Project solution created successfully'));
    }

    public function read(ReadProjectSolutionRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(ProjectSolutionResource::make($request->getProjectSolution()));
    }

    public function delete(DeleteProjectSolutionRequest $request): JsonResponse
    {
        $this->projectSolutionService->delete($request->route('id'));

        return $this->sendSuccess(__('Project solution deleted successfully'));
    }
}
