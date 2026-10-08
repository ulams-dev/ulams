<?php

namespace Ulams\ConsultationAccess\Http\Controllers;

use Ulams\ConsultationAccess\Http\Controllers\Swagger\ConsultationAccessEnquiryApiSwagger;
use Ulams\ConsultationAccess\Http\Requests\DeleteConsultationAccessEnquiryRequest;
use Ulams\ConsultationAccess\Http\Requests\ListConsultationAccessEnquiryRequest;
use Ulams\ConsultationAccess\Http\Requests\ReadConsultationAccessEnquiryRequest;
use Ulams\ConsultationAccess\Http\Requests\UpdateConsultationAccessEnquiryRequest;
use Ulams\ConsultationAccess\Http\Resources\ConsultationAccessEnquiryResource;
use Ulams\ConsultationAccess\Http\Requests\CreateConsultationAccessEnquiryRequest;
use Ulams\ConsultationAccess\Http\Resources\JoinConsultationAccessResource;
use Ulams\ConsultationAccess\Services\Contracts\ConsultationAccessEnquiryServiceContract;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Http\JsonResponse;

class ConsultationAccessEnquiryApiController extends UlamsBaseController implements ConsultationAccessEnquiryApiSwagger
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
