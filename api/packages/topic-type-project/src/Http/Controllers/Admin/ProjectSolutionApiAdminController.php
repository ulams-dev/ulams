<?php

namespace Ulams\TopicTypeProject\Http\Controllers\Admin;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\TopicTypeProject\Http\Controllers\Admin\Swagger\ProjectSolutionApiAdminSwagger;
use Ulams\TopicTypeProject\Http\Requests\Admin\AdminDeleteProjectSolutionRequest;
use Ulams\TopicTypeProject\Http\Requests\Admin\AdminGradeProjectSolutionRequest;
use Ulams\TopicTypeProject\Http\Requests\Admin\AdminListProjectSolutionRequest;
use Ulams\TopicTypeProject\Http\Requests\Admin\AdminReadProjectSolutionRequest;
use Ulams\TopicTypeProject\Http\Requests\Admin\AdminUpdateProjectSolutionFeedbackRequest;
use Ulams\TopicTypeProject\Http\Resources\ProjectSolutionResource;
use Ulams\TopicTypeProject\Services\Contracts\ProjectSolutionServiceContract;
use Illuminate\Http\JsonResponse;

class ProjectSolutionApiAdminController extends UlamsBaseController implements ProjectSolutionApiAdminSwagger
{
    private ProjectSolutionServiceContract $projectSolutionService;

    public function __construct(ProjectSolutionServiceContract $projectSolutionService)
    {
        $this->projectSolutionService = $projectSolutionService;
    }

    public function index(AdminListProjectSolutionRequest $request): JsonResponse
    {
        $results = $this->projectSolutionService->findAll($request->getCriteria(), $request->getPage());

        return $this->sendResponseForResource(ProjectSolutionResource::collection($results));
    }

    public function read(AdminReadProjectSolutionRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(ProjectSolutionResource::make($request->getProjectSolution()));
    }

    public function feedback(AdminUpdateProjectSolutionFeedbackRequest $request): JsonResponse
    {
        $result = $this->projectSolutionService->updateFeedback($request->getId(), $request->getFeedback());

        return $this->sendResponseForResource(ProjectSolutionResource::make($result), __('Updated successfully'));
    }

    public function delete(AdminDeleteProjectSolutionRequest $request): JsonResponse
    {
        $this->projectSolutionService->delete($request->route('id'));

        return $this->sendSuccess(__('Project solution deleted successfully'));
    }

    public function grade(AdminGradeProjectSolutionRequest $request): JsonResponse
    {
        $solution = $this->projectSolutionService->grade(
            (int) $request->route('id'),
            $request->getGradeProjectSolutionDto()
        );

        return $this->sendResponseForResource(
            ProjectSolutionResource::make($solution),
            __('Project solution graded successfully')
        );
    }
}
