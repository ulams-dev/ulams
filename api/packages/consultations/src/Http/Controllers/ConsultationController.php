<?php

namespace Ulams\Consultations\Http\Controllers;

use Ulams\Auth\Dtos\Admin\UserAssignableDto;
use Ulams\Auth\Http\Resources\UserFullResource;
use Ulams\Auth\Services\Contracts\UserServiceContract;
use Ulams\Consultations\Dto\ChangeTermConsultationDto;
use Ulams\Consultations\Dto\ConsultationDto;
use Ulams\Consultations\Enum\ConstantEnum;
use Ulams\Consultations\Enum\ConsultationsPermissionsEnum;
use Ulams\Consultations\Http\Controllers\Swagger\ConsultationSwagger;
use Ulams\Consultations\Http\Requests\ChangeTermConsultationRequest;
use Ulams\Consultations\Http\Requests\ConsultationAssignableUserListRequest;
use Ulams\Consultations\Http\Requests\DestroyConsultationRequest;
use Ulams\Consultations\Http\Requests\ListConsultationsRequest;
use Ulams\Consultations\Http\Requests\ScheduleConsultationRequest;
use Ulams\Consultations\Http\Requests\ShowConsultationRequest;
use Ulams\Consultations\Http\Requests\StoreConsultationRequest;
use Ulams\Consultations\Http\Requests\UpdateConsultationRequest;
use Ulams\Consultations\Http\Resources\ConsultationSimpleResource;
use Ulams\Consultations\Http\Resources\ConsultationTermsResource;
use Ulams\Consultations\Http\Resources\ConsultationUserTermsResource;
use Ulams\Consultations\Services\Contracts\ConsultationServiceContract;
use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Http\JsonResponse;

class ConsultationController extends UlamsBaseController implements ConsultationSwagger
{
    private ConsultationServiceContract $consultationServiceContract;
    private UserServiceContract $userService;

    public function __construct(
        ConsultationServiceContract $consultationServiceContract,
        UserServiceContract $userService
    ) {
        $this->consultationServiceContract = $consultationServiceContract;
        $this->userService = $userService;
    }

    public function index(ListConsultationsRequest $listConsultationsRequest): JsonResponse
    {
        $search = $listConsultationsRequest->except(['limit', 'skip']);
        $consultations = $this->consultationServiceContract
            ->getConsultationsList($search, false, OrderDto::instantiateFromRequest($listConsultationsRequest))
            ->paginate(
                $listConsultationsRequest->get('per_page') ??
                config('ulams_consultations.perPage', ConstantEnum::PER_PAGE)
            );

        return $this->sendResponseForResource(
            ConsultationSimpleResource::collection($consultations), __('Consultations retrieved successfully')
        );
    }

    public function schedule(int $id, ScheduleConsultationRequest $scheduleConsultationRequest): JsonResponse
    {
        $search = $scheduleConsultationRequest->except(['limit', 'skip', 'order', 'order_by']);

        $consultationUserTerms = $this->consultationServiceContract
            ->getConsultationTermsByConsultationId($id, $search);

        return $this->sendResponseForResource(
            ConsultationUserTermsResource::collection($consultationUserTerms),
            __('Consultation schedules retrieved successfully')
        );
    }

    public function store(StoreConsultationRequest $storeConsultationRequest): JsonResponse
    {
        $dto = new ConsultationDto($storeConsultationRequest->all());
        $consultation = $this->consultationServiceContract->store($dto);
        $this->consultationServiceContract->updateModelFieldsFromRequest($consultation, $storeConsultationRequest);
        return $this->sendResponseForResource(
            ConsultationSimpleResource::make($consultation),
            __('Consultation saved successfully')
        );
    }

    public function update(int $id, UpdateConsultationRequest $updateConsultationRequest): JsonResponse
    {
        $dto = new ConsultationDto($updateConsultationRequest->all());
        $consultation = $this->consultationServiceContract->update($id, $dto);
        $this->consultationServiceContract->updateModelFieldsFromRequest($consultation, $updateConsultationRequest);
        return $this->sendResponseForResource(
            ConsultationSimpleResource::make($consultation),
            __('Consultation updated successfully')
        );
    }

    public function show(ShowConsultationRequest $showConsultationRequest, int $id): JsonResponse
    {
        $consultation = $this->consultationServiceContract->show($id);
        return $this->sendResponseForResource(
            ConsultationSimpleResource::make($consultation),
            __('Consultation show successfully')
        );
    }

    public function destroy(int $id, DestroyConsultationRequest $request): JsonResponse
    {
        $this->consultationServiceContract->delete($id);
        return $this->sendSuccess(__('Consultation deleted successfully'));
    }

    public function changeTerm(ChangeTermConsultationRequest $changeTermConsultationRequest, int $consultationTermId): JsonResponse
    {
        $this->consultationServiceContract->changeTerm(
            $consultationTermId,
            new ChangeTermConsultationDto($changeTermConsultationRequest->all())
        );
        return $this->sendSuccess(__('Consultation term changed successfully'));
    }

    public function assignableUsers(ConsultationAssignableUserListRequest $request): JsonResponse
    {
        $dto = UserAssignableDto::instantiateFromArray(array_merge($request->validated(), ['assignable_by' => ConsultationsPermissionsEnum::CONSULTATION_CREATE]));
        $result = $this->userService
            ->assignableUsersWithCriteria($dto, $request->get('per_page'), $request->get('page'));
        return $this->sendResponseForResource(UserFullResource::collection($result), __('Users assignable to courses'));
    }
}
