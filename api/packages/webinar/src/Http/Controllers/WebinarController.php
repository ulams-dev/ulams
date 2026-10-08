<?php

namespace Ulams\Webinar\Http\Controllers;

use Ulams\Auth\Dtos\Admin\UserAssignableDto;
use Ulams\Auth\Http\Resources\UserFullResource;
use Ulams\Auth\Services\Contracts\UserServiceContract;
use Ulams\Webinar\Dto\WebinarUserDto;
use Ulams\Webinar\Http\Requests\DeleteWebinarRequest;
use Ulams\Webinar\Http\Requests\ShowWebinarRequest;
use Ulams\Core\Dtos\OrderDto;
use Ulams\Webinar\Enum\WebinarPermissionsEnum;
use Ulams\Webinar\Http\Requests\StoreWebinarRequest;
use Ulams\Webinar\Http\Requests\UpdateWebinarRequest;
use Ulams\Webinar\Dto\WebinarDto;
use Ulams\Webinar\Enum\ConstantEnum;
use Ulams\Webinar\Http\Controllers\Swagger\WebinarSwagger;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Webinar\Http\Requests\ListWebinarsRequest;
use Ulams\Webinar\Http\Requests\WebinarAssignableUserListRequest;
use Ulams\Webinar\Http\Requests\WebinarUserRequest;
use Ulams\Webinar\Http\Resources\WebinarSimpleResource;
use Ulams\Webinar\Services\Contracts\WebinarServiceContract;
use Illuminate\Http\JsonResponse;

class WebinarController extends UlamsBaseController implements WebinarSwagger
{
    private WebinarServiceContract $webinarServiceContract;
    private UserServiceContract $userService;

    public function __construct(
        WebinarServiceContract $webinarServiceContract,
        UserServiceContract $userService
    ) {
        $this->webinarServiceContract = $webinarServiceContract;
        $this->userService = $userService;
    }

    public function index(ListWebinarsRequest $listWebinarsRequest): JsonResponse
    {
        $search = $listWebinarsRequest->except(['limit', 'skip', 'order', 'order_by']);
        $orderDto = OrderDto::instantiateFromRequest($listWebinarsRequest);
        $webinars = $this->webinarServiceContract
            ->getWebinarsList($search, false, $orderDto)
            ->paginate(
                $listWebinarsRequest->get('per_page') ??
                config('ulams_webinar.perPage', ConstantEnum::PER_PAGE)
            );

        return $this->sendResponseForResource(
            $this->webinarServiceContract->extendResponse(WebinarSimpleResource::collection($webinars)),
            __('Webinars retrieved successfully')
        );
    }

    public function store(StoreWebinarRequest $storeWebinarRequest): JsonResponse
    {
        $dto = new WebinarDto($storeWebinarRequest->all());
        $webinar = $this->webinarServiceContract->store($dto);
        return $this->sendResponseForResource(
            $this->webinarServiceContract->extendResponse(WebinarSimpleResource::make($webinar)),
            __('Webinar saved successfully')
        );
    }

    public function update(int $id, UpdateWebinarRequest $updateWebinarRequest): JsonResponse
    {
        $dto = new WebinarDto($updateWebinarRequest->all());
        $webinar = $this->webinarServiceContract->update($id, $dto);
        return $this->sendResponseForResource(
            $this->webinarServiceContract->extendResponse(WebinarSimpleResource::make($webinar)),
            __('Webinar updated successfully')
        );
    }

    public function show(int $id, ShowWebinarRequest $request): JsonResponse
    {
        $webinar = $this->webinarServiceContract->show($id);
        return $this->sendResponseForResource(
            $this->webinarServiceContract->extendResponse(WebinarSimpleResource::make($webinar)),
            __('Webinar updated successfully')
        );
    }

    public function destroy(int $id, DeleteWebinarRequest $request): JsonResponse
    {
        $this->webinarServiceContract->delete($id);
        return $this->sendSuccess(__('Webinar deleted successfully'));
    }

    public function assignableUsers(WebinarAssignableUserListRequest $request): JsonResponse
    {
        $dto = UserAssignableDto::instantiateFromArray(array_merge($request->validated(), ['assignable_by' => WebinarPermissionsEnum::WEBINAR_CREATE]));
        $result = $this->userService
            ->assignableUsersWithCriteria($dto, $request->get('per_page'), $request->get('page'));
        return $this->sendResponseForResource(UserFullResource::collection($result), __('Users assignable to courses'));
    }

    public function webinarUsers(int $id, WebinarUserRequest $request): JsonResponse
    {
        $dto = WebinarUserDto::instantiateFromArray(array_merge($request->except(['limit', 'skip', 'order', 'order_by']), ['webinar_id' => $id]));
        $orderDto = OrderDto::instantiateFromRequest($request);
        $result = $this->userService
            ->assignableUsersWithCriteria($dto, $request->get('per_page'), $request->get('page'), $orderDto);
        return $this->sendResponseForResource(UserFullResource::collection($result), __('Webinar Users retrieved successfully'));
    }
}
