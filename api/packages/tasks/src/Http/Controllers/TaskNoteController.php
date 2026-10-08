<?php

namespace Ulams\Tasks\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Tasks\Http\Controllers\Swagger\TaskNoteControllerSwagger;
use Ulams\Tasks\Http\Requests\CreateTaskNoteRequest;
use Ulams\Tasks\Http\Requests\DeleteTaskNoteRequest;
use Ulams\Tasks\Http\Requests\UpdateTaskNoteRequest;
use Ulams\Tasks\Http\Resources\TaskNoteResource;
use Ulams\Tasks\Services\Contracts\TaskNoteServiceContract;
use Illuminate\Http\JsonResponse;

class TaskNoteController extends UlamsBaseController implements TaskNoteControllerSwagger
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
