<?php

namespace Ulams\Tasks\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Tasks\Http\Controllers\Swagger\TaskControllerSwagger;
use Ulams\Tasks\Http\Requests\CompleteTaskRequest;
use Ulams\Tasks\Http\Requests\DeleteTaskRequest;
use Ulams\Tasks\Http\Requests\DetailsTaskRequest;
use Ulams\Tasks\Http\Requests\IncompleteTaskRequest;
use Ulams\Tasks\Http\Requests\ListTaskRequest;
use Ulams\Tasks\Http\Resources\TaskDetailsResource;
use Ulams\Tasks\Http\Resources\TaskResource;
use Ulams\Tasks\Http\Requests\CreateTaskRequest;
use Ulams\Tasks\Http\Requests\UpdateTaskRequest;
use Ulams\Tasks\Services\TaskService;
use Illuminate\Http\JsonResponse;

class TaskController extends UlamsBaseController implements TaskControllerSwagger
{
    private TaskService $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    public function create(CreateTaskRequest $request): JsonResponse
    {
        $task = $this->taskService->create($request->toDto());

        return $this->sendResponseForResource(TaskResource::make($task), __('User task created successfully.'));
    }

    public function update(UpdateTaskRequest $request): JsonResponse
    {
        $task = $this->taskService->update($request->toDto());

        return $this->sendResponseForResource(TaskResource::make($task), __('User task updated successfully.'));
    }

    public function delete(DeleteTaskRequest $request): JsonResponse
    {
        $this->taskService->delete($request->getId());

        return $this->sendSuccess(__('User task deleted successfully.'));
    }

    public function complete(CompleteTaskRequest $request): JsonResponse
    {
        $task = $this->taskService->completeOwn($request->getId());

        return $this->sendResponseForResource(TaskResource::make($task), __('User task complete successfully.'));
    }

    public function incomplete(IncompleteTaskRequest $request): JsonResponse
    {
        $task = $this->taskService->incomplete($request->getId());

        return $this->sendResponseForResource(TaskResource::make($task), __('User task incomplete successfully.'));
    }

    public function findAll(ListTaskRequest $request): JsonResponse
    {
        $collection = $this->taskService->findAllByUser($request->getPage(), $request->getCriteria(), $request->getOrder());

        return $this->sendResponseForResource(TaskResource::collection($collection));
    }

    public function find(DetailsTaskRequest $request): JsonResponse
    {
        $result = $this->taskService->find($request->getId());

        return $this->sendResponseForResource(TaskDetailsResource::make($result));
    }
}
