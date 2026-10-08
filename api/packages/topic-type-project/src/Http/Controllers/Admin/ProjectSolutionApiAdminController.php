<?php

namespace EscolaLms\TopicTypeProject\Http\Controllers\Admin;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\TopicTypeProject\Http\Controllers\Admin\Swagger\ProjectSolutionApiAdminSwagger;
use EscolaLms\TopicTypeProject\Http\Requests\Admin\AdminDeleteProjectSolutionRequest;
use EscolaLms\TopicTypeProject\Http\Requests\Admin\AdminGradeProjectSolutionRequest;
use EscolaLms\TopicTypeProject\Http\Requests\Admin\AdminListProjectSolutionRequest;
use EscolaLms\TopicTypeProject\Http\Requests\Admin\AdminReadProjectSolutionRequest;
use EscolaLms\TopicTypeProject\Http\Requests\Admin\AdminUpdateProjectSolutionFeedbackRequest;
use EscolaLms\TopicTypeProject\Http\Resources\ProjectSolutionResource;
use EscolaLms\TopicTypeProject\Services\Contracts\ProjectSolutionServiceContract;
use Illuminate\Http\JsonResponse;

class ProjectSolutionApiAdminController extends EscolaLmsBaseController implements ProjectSolutionApiAdminSwagger
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
