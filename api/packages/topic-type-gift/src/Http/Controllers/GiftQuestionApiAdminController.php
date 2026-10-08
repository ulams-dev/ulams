<?php

namespace Ulams\TopicTypeGift\Http\Controllers;

use Carbon\Carbon;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\TopicTypeGift\Export\QuestionExport;
use Ulams\TopicTypeGift\Http\Controllers\Swagger\GiftQuestionApiAdminSwagger;
use Ulams\TopicTypeGift\Http\Requests\Admin\AdminCreateGiftQuestionRequest;
use Ulams\TopicTypeGift\Http\Requests\Admin\AdminDeleteGiftQuestionRequest;
use Ulams\TopicTypeGift\Http\Requests\Admin\AdminExportGiftQuestionsRequest;
use Ulams\TopicTypeGift\Http\Requests\Admin\AdminImportGiftQuestionsRequest;
use Ulams\TopicTypeGift\Http\Requests\Admin\AdminSortGiftQuestionRequest;
use Ulams\TopicTypeGift\Http\Requests\Admin\AdminUpdateGiftQuestionRequest;
use Ulams\TopicTypeGift\Http\Resources\AdminGiftQuestionResource;
use Ulams\TopicTypeGift\Import\QuestionImport;
use Ulams\TopicTypeGift\Services\Contracts\GiftQuestionServiceContract;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GiftQuestionApiAdminController extends UlamsBaseController implements GiftQuestionApiAdminSwagger
{
    private GiftQuestionServiceContract $giftQuestionService;

    public function __construct(GiftQuestionServiceContract $giftQuestionService)
    {
        $this->giftQuestionService = $giftQuestionService;
    }

    public function create(AdminCreateGiftQuestionRequest $request): JsonResponse
    {
        $result = $this->giftQuestionService->create($request->getGiftQuestionDto());

        return $this->sendResponseForResource(AdminGiftQuestionResource::make($result), __('Gift question created successfully.'));
    }

    public function update(AdminUpdateGiftQuestionRequest $request): JsonResponse
    {
        $result = $this->giftQuestionService->update($request->getGiftQuestionDto(), $request->getId());

        return $this->sendResponseForResource(AdminGiftQuestionResource::make($result), __('Gift question updated successfully.'));
    }

    public function delete(AdminDeleteGiftQuestionRequest $request): JsonResponse
    {
        $this->giftQuestionService->delete($request->getId());

        return $this->sendSuccess(__('Gift question deleted successfully.'));
    }

    public function sort(AdminSortGiftQuestionRequest $request): JsonResponse
    {
        $this->giftQuestionService->sort($request->toDto());

        return $this->sendSuccess(__('Gift questions sorted successfully.'));
    }

    public function export(AdminExportGiftQuestionsRequest $request): BinaryFileResponse
    {
        return Excel::download(
            new QuestionExport($request->toDto()),
            'questions.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    public function import(AdminImportGiftQuestionsRequest $request): JsonResponse
    {
        Excel::import(new QuestionImport($request->getQuizId()), $request->getFile());

        return $this->sendSuccess(__('Gift questions imported successfully.'));
    }
}
