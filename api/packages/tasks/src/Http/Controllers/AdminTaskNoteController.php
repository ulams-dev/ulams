<?php

namespace EscolaLms\Tasks\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Tasks\Http\Controllers\Swagger\AdminTaskNoteControllerSwagger;
use EscolaLms\Tasks\Http\Requests\Admin\AdminCreateTaskNoteRequest;
use EscolaLms\Tasks\Http\Requests\Admin\AdminDeleteTaskNoteRequest;
use EscolaLms\Tasks\Http\Requests\Admin\AdminUpdateTaskNoteRequest;
use EscolaLms\Tasks\Http\Resources\TaskNoteResource;
use EscolaLms\Tasks\Services\Contracts\TaskNoteServiceContract;
use Illuminate\Http\JsonResponse;

class AdminTaskNoteController extends EscolaLmsBaseController implements AdminTaskNoteControllerSwagger
{
    private TaskNoteServiceContract $taskNoteService;

    public function __construct(TaskNoteServiceContract $taskNoteService)
    {
        $this->taskNoteService = $taskNoteService;
    }

    public function create(AdminCreateTaskNoteRequest $request): JsonResponse
    {
        $taskNote = $this->taskNoteService->create($request->toDto());

        return $this->sendResponseForResource(TaskNoteResource::make($taskNote), __('Note created successfully.'));
    }

    public function update(AdminUpdateTaskNoteRequest $request): JsonResponse
    {
        $taskNote = $this->taskNoteService->update($request->toDto());

        return $this->sendResponseForResource(TaskNoteResource::make($taskNote), __('Note updated successfully.'));
    }

    public function delete(AdminDeleteTaskNoteRequest $request): JsonResponse
    {
        $this->taskNoteService->delete($request->getId());

        return $this->sendSuccess(__('Note deleted successfully.'));
    }
}
