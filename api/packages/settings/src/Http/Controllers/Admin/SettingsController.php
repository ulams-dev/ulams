<?php

namespace EscolaLms\Settings\Http\Controllers\Admin;

// use EscolaLms\Settings\Http\Controllers\Swagger\LessonAPISwagger;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Settings\Http\Controllers\Admin\Swagger\SettingsControllerContract;
use EscolaLms\Settings\Http\Requests\Admin\SettingsCreateRequest;
use EscolaLms\Settings\Http\Requests\Admin\SettingsDeleteRequest;
use EscolaLms\Settings\Http\Requests\Admin\SettingsListRequest;
use EscolaLms\Settings\Http\Requests\Admin\SettingsReadRequest;
use EscolaLms\Settings\Http\Requests\Admin\SettingsUpdateRequest;
use EscolaLms\Settings\Http\Resources\SettingResource;
use EscolaLms\Settings\Repositories\Contracts\SettingsRepositoryContract;
use EscolaLms\Settings\Services\Contracts\SettingsServiceContract;

use Error;
use Illuminate\Http\JsonResponse;

class SettingsController extends EscolaLmsBaseController implements SettingsControllerContract
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
