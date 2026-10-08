<?php

namespace Ulams\Questionnaire\Http\Controllers;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Questionnaire\Dtos\QuestionAnswerFilterCriteriaDto;
use Ulams\Questionnaire\Http\Controllers\Contracts\QuestionAnswerAdminApiContract;
use Ulams\Questionnaire\Http\Requests\QuestionAnswerListingRequest;
use Ulams\Questionnaire\Http\Requests\QuestionAnswerVisibilityRequest;
use Ulams\Questionnaire\Http\Resources\QuestionAnswerResource;
use Ulams\Questionnaire\Repository\Contracts\QuestionAnswerRepositoryContract;
use Ulams\Questionnaire\Services\Contracts\QuestionnaireAnswerServiceContract;
use Illuminate\Http\JsonResponse;

class QuestionAnswerAdminApiController extends UlamsBaseController implements QuestionAnswerAdminApiContract
{
    private QuestionnaireAnswerServiceContract $questionnaireAnswerService;
    private QuestionAnswerRepositoryContract $questionAnswerRepository;

    public function __construct(QuestionnaireAnswerServiceContract $questionnaireAnswerService, QuestionAnswerRepositoryContract $questionAnswerRepository)
    {
        $this->questionnaireAnswerService = $questionnaireAnswerService;
        $this->questionAnswerRepository = $questionAnswerRepository;
    }

    public function list(int $id, QuestionAnswerListingRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(
            QuestionAnswerResource::collection($this->questionAnswerRepository->listWithCriteriaAndOrder(
                $id,
                QuestionAnswerFilterCriteriaDto::instantiateFromRequest($request),
                OrderDto::instantiateFromRequest($request),
                $request->get('per_page', 20)
            )),
            __("Question answers list retrieved successfully")
        );
    }

    public function changeAnswerVisibility(QuestionAnswerVisibilityRequest $request, int $id): JsonResponse
    {
        return $this->sendResponseForResource(
            QuestionAnswerResource::make($this->questionnaireAnswerService->update($request->validated(), $id)),
            __('Question answers visibility changed successfully')
        );
    }
}
