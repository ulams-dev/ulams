<?php

namespace Ulams\ConsultationAccess\Http\Controllers\Admin;

use Ulams\ConsultationAccess\Exceptions\ConsultationAccessException;
use Ulams\ConsultationAccess\Http\Controllers\Admin\Swagger\ConsultationAccessEnquiryAdminApiSwagger;
use Ulams\ConsultationAccess\Http\Requests\Admin\AdminApproveConsultationAccessEnquiryRequest;
use Ulams\ConsultationAccess\Http\Requests\Admin\AdminDisapproveConsultationAccessEnquiryRequest;
use Ulams\ConsultationAccess\Http\Requests\Admin\AdminListConsultationAccessEnquiryRequest;
use Ulams\ConsultationAccess\Http\Resources\ConsultationAccessEnquiryResource;
use Ulams\ConsultationAccess\Services\Contracts\ConsultationAccessEnquiryServiceContract;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Http\JsonResponse;

class ConsultationAccessEnquiryAdminApiController extends UlamsBaseController implements ConsultationAccessEnquiryAdminApiSwagger
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
