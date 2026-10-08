<?php

namespace Ulams\Questionnaire\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Questionnaire\Dtos\QuestionAnswersCriteriaDto;
use Ulams\Questionnaire\Dtos\QuestionnaireFrontFilterCriteriaDto;
use Ulams\Questionnaire\Http\Controllers\Contracts\QuestionnaireApiContract;
use Ulams\Questionnaire\Http\Requests\QuestionAnswersFrontReadRequest;
use Ulams\Questionnaire\Http\Requests\QuestionAnswersFrontStarsRequest;
use Ulams\Questionnaire\Http\Requests\QuestionnaireFrontAnswerRequest;
use Ulams\Questionnaire\Http\Requests\QuestionnaireFrontListingRequest;
use Ulams\Questionnaire\Http\Requests\QuestionnaireFrontReadRequest;
use Ulams\Questionnaire\Http\Requests\QuestionnaireStarsFrontRequest;
use Ulams\Questionnaire\Http\Resources\ModelStarsResponse;
use Ulams\Questionnaire\Http\Resources\QuestionAnswerFrontResource;
use Ulams\Questionnaire\Http\Resources\QuestionnaireFrontResource;
use Ulams\Questionnaire\Http\Resources\QuestionnaireResource;
use Ulams\Questionnaire\Http\Resources\QuestionnaireStarsResource;
use Ulams\Questionnaire\Models\Question;
use Ulams\Questionnaire\Models\QuestionnaireModel;
use Ulams\Questionnaire\Services\Contracts\QuestionnaireAnswerServiceContract;
use Ulams\Questionnaire\Services\Contracts\QuestionnaireServiceContract;
use Ulams\Questionnaire\Services\Contracts\QuestionServiceContract;
use Illuminate\Http\JsonResponse;

class QuestionnaireApiController extends UlamsBaseController implements QuestionnaireApiContract
{
    private QuestionnaireServiceContract $questionnaireService;
    private QuestionnaireAnswerServiceContract $questionnaireAnswerService;
    private QuestionServiceContract $questionService;
    public function __construct(
        QuestionnaireServiceContract $questionnaireService,
        QuestionnaireAnswerServiceContract $questionnaireAnswerService,
        QuestionServiceContract $questionService
    ) {
        $this->questionnaireService = $questionnaireService;
        $this->questionService = $questionService;
        $this->questionnaireAnswerService = $questionnaireAnswerService;
    }

    public function list(QuestionnaireFrontListingRequest $request): JsonResponse
    {
        $questionnaires = $this->questionnaireService->searchForFront(
            QuestionnaireFrontFilterCriteriaDto::instantiateFromRequest($request)->toArray(),
            $request->input('public_answers')
        );

        return $this->sendResponseForResource(
            QuestionnaireResource::collection($questionnaires),
            __("Questionnaire list retrieved successfully")
        );
    }

    public function read(QuestionnaireFrontReadRequest $request): JsonResponse
    {
        $questionnaire = $this->questionnaireService->findForFront(
            [
                'questionnaire_id' => $request->getParamId(),
                'model_type_title' => $request->getParamModelTypeTitle(),
                'model_id' => $request->getParamModelId(),
                'active' => true
            ],
            $request->user()
        );

        return $this->sendResponseForResource(
            QuestionnaireFrontResource::make($questionnaire),
            __("questionnaire fetched successfully")
        );
    }

    public function answer(QuestionnaireFrontAnswerRequest $request): JsonResponse
    {
        /** @var QuestionnaireModel $questionnaireModel */
        $questionnaireModel = QuestionnaireModel::query()->where([
            'questionnaire_id' => $request->getParamId(),
            'model_id' => $request->getParamModelId(),
            'model_type_id' => $request->getQuestionnaireModelType()->id,
        ])->firstOrFail();

        $questionnaire = $this->questionnaireAnswerService->saveAnswer(
            $questionnaireModel,
            $request->validated(),
            $request->user()
        );

        return $this->sendResponseForResource(
            QuestionnaireFrontResource::make($questionnaire),
            __("Answers save successfully")
        );
    }

    public function stars(QuestionnaireStarsFrontRequest $request): JsonResponse
    {
        $report = $this->questionnaireAnswerService->getStars(
            $request->getQuestionnaireModelType()->id,
            $request->getParamModelId()
        );

        return $this->sendResponseForResource(
            QuestionnaireStarsResource::make($report),
            __("Questionnaire report fetched successfully")
        );
    }

    public function questionModelAnswers(QuestionAnswersFrontReadRequest $request): JsonResponse
    {
        $answers = $this
            ->questionnaireAnswerService
            ->publicQuestionAnswers(QuestionAnswersCriteriaDto::instantiateFromRequest($request)->toArray(), $request->input('per_page'));
        return $this->sendResponseForResource(
            QuestionAnswerFrontResource::collection($answers),
            __('Question answers fetched successfully')
        );
    }

    public function modelStars(QuestionAnswersFrontStarsRequest $request): JsonResponse
    {
        $result = $this->questionnaireAnswerService->getReviewStars(QuestionAnswersCriteriaDto::instantiateFromRequest($request)->toArray());
        $result['max_score'] = $this->questionService->getQuestionMaxScore($request->question_id);
        return $this->sendResponseForResource(
            ModelStarsResponse::make($result),
            __('Model stars fetched successfully'),
        );
    }
}
