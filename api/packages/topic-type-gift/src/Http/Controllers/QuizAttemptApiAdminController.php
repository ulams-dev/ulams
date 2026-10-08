<?php

namespace EscolaLms\TopicTypeGift\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\TopicTypeGift\Export\QuizResultsExport;
use EscolaLms\TopicTypeGift\Http\Controllers\Swagger\QuizAttemptApiAdminSwagger;
use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminExportQuizResultsRequest;
use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminListQuizAttemptRequest;
use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminReadQuizAttemptRequest;
use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminUpdateQuizAttemptFeedbackRequest;
use EscolaLms\TopicTypeGift\Http\Resources\QuizAttemptResource;
use EscolaLms\TopicTypeGift\Http\Resources\QuizAttemptSimpleResource;
use EscolaLms\TopicTypeGift\Services\Contracts\QuizAttemptServiceContract;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuizAttemptApiAdminController extends EscolaLmsBaseController implements QuizAttemptApiAdminSwagger
{
    private QuizAttemptServiceContract $attemptService;

    public function __construct(QuizAttemptServiceContract $attemptService)
    {
        $this->attemptService = $attemptService;
    }

    public function index(AdminListQuizAttemptRequest $request): JsonResponse
    {
        $result = $this->attemptService->findAll($request->getCriteriaDto(), $request->getPageDto(), $request->getOrderDto());

        return $this->sendResponseForResource(QuizAttemptSimpleResource::collection($result));
    }

    public function read(AdminReadQuizAttemptRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(QuizAttemptResource::make($request->getAttempt()));
    }

    public function feedback(AdminUpdateQuizAttemptFeedbackRequest $request): JsonResponse
    {
        $result = $this->attemptService->updateFeedback($request->getId(), $request->getFeedback());

        return $this->sendResponseForResource(QuizAttemptResource::make($result), __('Updated successfully'));
    }

    public function export(AdminExportQuizResultsRequest $request): BinaryFileResponse
    {
        $format = $request->getExportFormat();

        $writerType = $format === AdminExportQuizResultsRequest::FORMAT_XLS
            ? \Maatwebsite\Excel\Excel::XLS
            : \Maatwebsite\Excel\Excel::XLSX;

        return Excel::download(
            new QuizResultsExport($request->getCourseId(), $request->getQuizId(), $request->getAuthorId()),
            'quiz-results.' . $format,
            $writerType
        );
    }
}
