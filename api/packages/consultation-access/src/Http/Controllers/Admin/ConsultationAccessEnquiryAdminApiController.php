<?php

namespace EscolaLms\ConsultationAccess\Http\Controllers\Admin;

use EscolaLms\ConsultationAccess\Exceptions\ConsultationAccessException;
use EscolaLms\ConsultationAccess\Http\Controllers\Admin\Swagger\ConsultationAccessEnquiryAdminApiSwagger;
use EscolaLms\ConsultationAccess\Http\Requests\Admin\AdminApproveConsultationAccessEnquiryRequest;
use EscolaLms\ConsultationAccess\Http\Requests\Admin\AdminDisapproveConsultationAccessEnquiryRequest;
use EscolaLms\ConsultationAccess\Http\Requests\Admin\AdminListConsultationAccessEnquiryRequest;
use EscolaLms\ConsultationAccess\Http\Resources\ConsultationAccessEnquiryResource;
use EscolaLms\ConsultationAccess\Services\Contracts\ConsultationAccessEnquiryServiceContract;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use Illuminate\Http\JsonResponse;

class ConsultationAccessEnquiryAdminApiController extends EscolaLmsBaseController implements ConsultationAccessEnquiryAdminApiSwagger
{
    private ConsultationAccessEnquiryServiceContract $service;

    public function __construct(ConsultationAccessEnquiryServiceContract $service)
    {
        $this->service = $service;
    }

    public function index(AdminListConsultationAccessEnquiryRequest $request): JsonResponse
    {
        $result = $this->service->findAll($request->getCriteriaDto(), $request->getPaginationDto(), auth()->id(), $request->getOrderDto());

        return $this->sendResponseForResource(ConsultationAccessEnquiryResource::collection($result));
    }

    public function approve(AdminApproveConsultationAccessEnquiryRequest $request): JsonResponse
    {
        try {
            $this->service->approveByProposedTerm($request->getApproveConsultationAccessEnquiryDto());
            return $this->sendSuccess(__('Approved successfully.'));
        } catch (ConsultationAccessException $e) {
            return $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    public function disapprove(AdminDisapproveConsultationAccessEnquiryRequest $request): JsonResponse
    {
        $this->service->disapprove($request->getConsultationAccessEnquiryId(), $request->get('message'));
        return $this->sendSuccess(__('Enquiry disapproved'));
    }
}
