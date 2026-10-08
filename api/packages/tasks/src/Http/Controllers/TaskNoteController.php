<?php

namespace EscolaLms\Tasks\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Tasks\Http\Controllers\Swagger\TaskNoteControllerSwagger;
use EscolaLms\Tasks\Http\Requests\CreateTaskNoteRequest;
use EscolaLms\Tasks\Http\Requests\DeleteTaskNoteRequest;
use EscolaLms\Tasks\Http\Requests\UpdateTaskNoteRequest;
use EscolaLms\Tasks\Http\Resources\TaskNoteResource;
use EscolaLms\Tasks\Services\Contracts\TaskNoteServiceContract;
use Illuminate\Http\JsonResponse;

class TaskNoteController extends EscolaLmsBaseController implements TaskNoteControllerSwagger
{
    private TaskNoteServiceContract $taskNoteService;

    public function __construct(TaskNoteServiceContract $taskNoteService)
    {
        $this->taskNoteService = $taskNoteService;
    }

    public function create(CreateTaskNoteRequest $request): JsonResponse
    {
        $taskNote = $this->taskNoteService->create($request->toDto());

        return $this->sendResponseForResource(TaskNoteResource::make($taskNote), __('Note created successfully.'));
    }

    public function update(UpdateTaskNoteRequest $request): JsonResponse
    {
        $taskNote = $this->taskNoteService->update($request->toDto());

        return $this->sendResponseForResource(TaskNoteResource::make($taskNote), __('Note updated successfully.'));
    }

    public function delete(DeleteTaskNoteRequest $request): JsonResponse
    {
        $this->taskNoteService->delete($request->getId());

        return $this->sendSuccess(__('Note deleted successfully.'));
    }
}
