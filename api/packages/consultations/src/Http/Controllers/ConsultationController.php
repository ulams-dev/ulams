<?php

namespace EscolaLms\Consultations\Http\Controllers;

use EscolaLms\Auth\Dtos\Admin\UserAssignableDto;
use EscolaLms\Auth\Http\Resources\UserFullResource;
use EscolaLms\Auth\Services\Contracts\UserServiceContract;
use EscolaLms\Consultations\Dto\ChangeTermConsultationDto;
use EscolaLms\Consultations\Dto\ConsultationDto;
use EscolaLms\Consultations\Enum\ConstantEnum;
use EscolaLms\Consultations\Enum\ConsultationsPermissionsEnum;
use EscolaLms\Consultations\Http\Controllers\Swagger\ConsultationSwagger;
use EscolaLms\Consultations\Http\Requests\ChangeTermConsultationRequest;
use EscolaLms\Consultations\Http\Requests\ConsultationAssignableUserListRequest;
use EscolaLms\Consultations\Http\Requests\DestroyConsultationRequest;
use EscolaLms\Consultations\Http\Requests\ListConsultationsRequest;
use EscolaLms\Consultations\Http\Requests\ScheduleConsultationRequest;
use EscolaLms\Consultations\Http\Requests\ShowConsultationRequest;
use EscolaLms\Consultations\Http\Requests\StoreConsultationRequest;
use EscolaLms\Consultations\Http\Requests\UpdateConsultationRequest;
use EscolaLms\Consultations\Http\Resources\ConsultationSimpleResource;
use EscolaLms\Consultations\Http\Resources\ConsultationTermsResource;
use EscolaLms\Consultations\Http\Resources\ConsultationUserTermsResource;
use EscolaLms\Consultations\Services\Contracts\ConsultationServiceContract;
use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use Illuminate\Http\JsonResponse;

class ConsultationController extends EscolaLmsBaseController implements ConsultationSwagger
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
                config('escolalms_consultations.perPage', ConstantEnum::PER_PAGE)
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
