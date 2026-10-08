<?php

namespace Ulams\TopicTypeProject\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\TopicTypeProject\Http\Controllers\Swagger\ProjectSolutionApiSwagger;
use Ulams\TopicTypeProject\Http\Requests\CreateProjectSolutionRequest;
use Ulams\TopicTypeProject\Http\Requests\DeleteProjectSolutionRequest;
use Ulams\TopicTypeProject\Http\Requests\ListProjectSolutionRequest;
use Ulams\TopicTypeProject\Http\Requests\ReadProjectSolutionRequest;
use Ulams\TopicTypeProject\Http\Resources\ProjectSolutionResource;
use Ulams\TopicTypeProject\Services\Contracts\ProjectSolutionServiceContract;
use Illuminate\Http\JsonResponse;

class ProjectSolutionApiController extends UlamsBaseController implements ProjectSolutionApiSwagger
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
