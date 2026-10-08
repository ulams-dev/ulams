<?php

namespace Ulams\Tasks\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Tasks\Http\Controllers\Swagger\AdminTaskControllerSwagger;
use Ulams\Tasks\Http\Requests\Admin\AdminCompleteTaskRequest;
use Ulams\Tasks\Http\Requests\Admin\AdminDeleteTaskRequest;
use Ulams\Tasks\Http\Requests\Admin\AdminIncompleteTaskRequest;
use Ulams\Tasks\Http\Requests\Admin\AdminListTaskRequest;
use Ulams\Tasks\Http\Requests\Admin\AdminDetailsTaskRequest;
use Ulams\Tasks\Http\Resources\TaskDetailsResource;
use Ulams\Tasks\Http\Resources\TaskResource;
use Ulams\Tasks\Http\Requests\Admin\AdminCreateTaskRequest;
use Ulams\Tasks\Http\Requests\Admin\AdminUpdateTaskRequest;
use Ulams\Tasks\Services\TaskService;
use Illuminate\Http\JsonResponse;

class AdminTaskController extends UlamsBaseController implements AdminTaskControllerSwagger
{
    private TaskService $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    public function create(AdminCreateTaskRequest $request): JsonResponse
    {
        $task = $this->taskService->create($request->toDto());

        return $this->sendResponseForResource(TaskResource::make($task), __('Task created successfully.'));
    }

    public function update(AdminUpdateTaskRequest $request): JsonResponse
    {
        $task = $this->taskService->update($request->toDto());

        return $this->sendResponseForResource(TaskResource::make($task), __('Task updated successfully.'));
    }

    public function delete(AdminDeleteTaskRequest $request): JsonResponse
    {
        $this->taskService->delete($request->getId());

        return $this->sendSuccess(__('Task deleted successfully.'));
    }

    public function complete(AdminCompleteTaskRequest $request): JsonResponse
    {
        $task = $this->taskService->complete($request->getId());

        return $this->sendResponseForResource(TaskResource::make($task), __('Task complete successfully.'));
    }

    public function incomplete(AdminIncompleteTaskRequest $request): JsonResponse
    {
        $task = $this->taskService->incomplete($request->getId());

        return $this->sendResponseForResource(TaskResource::make($task), __('Task incomplete successfully.'));
    }

    public function findAll(AdminListTaskRequest $request): JsonResponse
    {
        $collection = $this->taskService->findAll($request->getPage(), $request->getCriteria(), $request->getOrder());

        return $this->sendResponseForResource(TaskResource::collection($collection));
    }

    public function find(AdminDetailsTaskRequest $request): JsonResponse
    {
        $result = $this->taskService->find($request->getId());

        return $this->sendResponseForResource(TaskDetailsResource::make($result));
    }
}
