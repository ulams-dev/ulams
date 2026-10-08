<?php

namespace Ulams\TopicTypeGift\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\TopicTypeGift\Export\QuizResultsExport;
use Ulams\TopicTypeGift\Http\Controllers\Swagger\QuizAttemptApiAdminSwagger;
use Ulams\TopicTypeGift\Http\Requests\Admin\AdminExportQuizResultsRequest;
use Ulams\TopicTypeGift\Http\Requests\Admin\AdminListQuizAttemptRequest;
use Ulams\TopicTypeGift\Http\Requests\Admin\AdminReadQuizAttemptRequest;
use Ulams\TopicTypeGift\Http\Requests\Admin\AdminUpdateQuizAttemptFeedbackRequest;
use Ulams\TopicTypeGift\Http\Resources\QuizAttemptResource;
use Ulams\TopicTypeGift\Http\Resources\QuizAttemptSimpleResource;
use Ulams\TopicTypeGift\Services\Contracts\QuizAttemptServiceContract;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuizAttemptApiAdminController extends UlamsBaseController implements QuizAttemptApiAdminSwagger
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
