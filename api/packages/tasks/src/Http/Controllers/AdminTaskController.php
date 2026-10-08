<?php

namespace EscolaLms\Tasks\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Tasks\Http\Controllers\Swagger\AdminTaskControllerSwagger;
use EscolaLms\Tasks\Http\Requests\Admin\AdminCompleteTaskRequest;
use EscolaLms\Tasks\Http\Requests\Admin\AdminDeleteTaskRequest;
use EscolaLms\Tasks\Http\Requests\Admin\AdminIncompleteTaskRequest;
use EscolaLms\Tasks\Http\Requests\Admin\AdminListTaskRequest;
use EscolaLms\Tasks\Http\Requests\Admin\AdminDetailsTaskRequest;
use EscolaLms\Tasks\Http\Resources\TaskDetailsResource;
use EscolaLms\Tasks\Http\Resources\TaskResource;
use EscolaLms\Tasks\Http\Requests\Admin\AdminCreateTaskRequest;
use EscolaLms\Tasks\Http\Requests\Admin\AdminUpdateTaskRequest;
use EscolaLms\Tasks\Services\TaskService;
use Illuminate\Http\JsonResponse;

class AdminTaskController extends EscolaLmsBaseController implements AdminTaskControllerSwagger
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
