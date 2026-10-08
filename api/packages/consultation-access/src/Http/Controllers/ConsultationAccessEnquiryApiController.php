<?php

namespace EscolaLms\ConsultationAccess\Http\Controllers;

use EscolaLms\ConsultationAccess\Http\Controllers\Swagger\ConsultationAccessEnquiryApiSwagger;
use EscolaLms\ConsultationAccess\Http\Requests\DeleteConsultationAccessEnquiryRequest;
use EscolaLms\ConsultationAccess\Http\Requests\ListConsultationAccessEnquiryRequest;
use EscolaLms\ConsultationAccess\Http\Requests\ReadConsultationAccessEnquiryRequest;
use EscolaLms\ConsultationAccess\Http\Requests\UpdateConsultationAccessEnquiryRequest;
use EscolaLms\ConsultationAccess\Http\Resources\ConsultationAccessEnquiryResource;
use EscolaLms\ConsultationAccess\Http\Requests\CreateConsultationAccessEnquiryRequest;
use EscolaLms\ConsultationAccess\Http\Resources\JoinConsultationAccessResource;
use EscolaLms\ConsultationAccess\Services\Contracts\ConsultationAccessEnquiryServiceContract;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use Illuminate\Http\JsonResponse;

class ConsultationAccessEnquiryApiController extends EscolaLmsBaseController implements ConsultationAccessEnquiryApiSwagger
{
    private ConsultationAccessEnquiryServiceContract $service;

    public function __construct(ConsultationAccessEnquiryServiceContract $service)
    {
        $this->service = $service;
    }

    public function index(ListConsultationAccessEnquiryRequest $request): JsonResponse
    {
        $result = $this->service->findByUser($request->getCriteriaDto(), $request->getPaginationDto(), auth()->id());

        return $this->sendResponseForResource(ConsultationAccessEnquiryResource::collection($result));
    }

    public function create(CreateConsultationAccessEnquiryRequest $request): JsonResponse
    {
        $result = $this->service->create($request->toDto());

        return $this->sendResponseForResource(ConsultationAccessEnquiryResource::make($result), __('Created successfully'));
    }

    public function delete(DeleteConsultationAccessEnquiryRequest $request): JsonResponse
    {
        $this->service->delete($request->getId());

        return $this->sendSuccess(__('Consultation access enquiry deleted successfully.'));
    }

    public function update(UpdateConsultationAccessEnquiryRequest $request): JsonResponse
    {
        $result = $this->service->update($request->getId(), $request->toDto());

        return $this->sendResponseForResource(ConsultationAccessEnquiryResource::make($result), __('Updated successfully'));
    }

    public function read(ReadConsultationAccessEnquiryRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(ConsultationAccessEnquiryResource::make($request->getEnquiry()));
    }

    public function join(ReadConsultationAccessEnquiryRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(JoinConsultationAccessResource::make($request->getEnquiry()));
    }
}
