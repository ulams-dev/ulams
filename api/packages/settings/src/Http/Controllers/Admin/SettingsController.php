<?php

namespace Ulams\Settings\Http\Controllers\Admin;

// use Ulams\Settings\Http\Controllers\Swagger\LessonAPISwagger;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Settings\Http\Controllers\Admin\Swagger\SettingsControllerContract;
use Ulams\Settings\Http\Requests\Admin\SettingsCreateRequest;
use Ulams\Settings\Http\Requests\Admin\SettingsDeleteRequest;
use Ulams\Settings\Http\Requests\Admin\SettingsListRequest;
use Ulams\Settings\Http\Requests\Admin\SettingsReadRequest;
use Ulams\Settings\Http\Requests\Admin\SettingsUpdateRequest;
use Ulams\Settings\Http\Resources\SettingResource;
use Ulams\Settings\Repositories\Contracts\SettingsRepositoryContract;
use Ulams\Settings\Services\Contracts\SettingsServiceContract;

use Error;
use Illuminate\Http\JsonResponse;

class SettingsController extends UlamsBaseController implements SettingsControllerContract
{
    private SettingsRepositoryContract $repository;
    private SettingsServiceContract $service;

    public function __construct(SettingsRepositoryContract $repository,  SettingsServiceContract $service)
    {
        $this->repository = $repository;
        $this->service = $service;
    }

    public function index(SettingsListRequest $request): JsonResponse
    {
        $search = $request->only(['group', 'key']);
        $settings = $this->service->searchAndPaginate($search, $request->input('per_page', 15));
        return $this->sendResponseForResource(SettingResource::collection($settings), __("Order search results"));
    }

    public function store(SettingsCreateRequest $request): JsonResponse
    {
        $input = $request->all();

        $setting = $this->repository->findOrCreate($input);

        return $this->sendResponse($setting->toArray(), __('Setting saved successfully'));
    }

    public function show($id, SettingsReadRequest $request): JsonResponse
    {

        $setting = $this->repository->find($id);

        if (empty($setting)) {
            return $this->sendError(__('Setting not found'), 404);
        }

        return $this->sendResponse($setting->toArray(), __('Setting retrieved successfully'));
    }

    public function update($id, SettingsUpdateRequest $request): JsonResponse
    {
        $input = $request->all();

        $setting = $this->repository->find($id);

        if (empty($setting)) {
            return $this->sendError(__('Setting not found'));
        }

        $setting = $this->repository->update($input, $id);

        return $this->sendResponse($setting->toArray(), __('Setting updated successfully'));
    }

    public function destroy($id, SettingsDeleteRequest $request): JsonResponse
    {
        $setting = $this->repository->find($id);

        if (empty($setting)) {
            return $this->sendError(__('Setting not found'));
        }

        $this->repository->delete($id);

        return $this->sendSuccess(__('Setting deleted successfully'));
    }

    public function groups(SettingsListRequest $request): JsonResponse
    {

        $groups = $this->service->groups();

        return $this->sendResponse($groups->toArray(), __('Settings groups retrieved successfully'));
    }
}
