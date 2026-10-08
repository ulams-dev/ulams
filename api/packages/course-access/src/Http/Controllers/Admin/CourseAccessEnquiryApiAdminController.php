<?php

namespace EscolaLms\CourseAccess\Http\Controllers\Admin;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\CourseAccess\Http\Controllers\Admin\Swagger\CourseAccessEnquiryApiAdminSwagger;
use EscolaLms\CourseAccess\Http\Requests\Admin\AdminApproveCourseAccessEnquiry;
use EscolaLms\CourseAccess\Http\Requests\Admin\AdminDeleteCourseAccessEnquiryRequest;
use EscolaLms\CourseAccess\Http\Requests\Admin\AdminListCourseAccessEnquiryRequest;
use EscolaLms\CourseAccess\Http\Resources\CourseAccessEnquiryResource;
use EscolaLms\CourseAccess\Services\Contracts\CourseAccessEnquiryServiceContract;
use Illuminate\Http\JsonResponse;

class CourseAccessEnquiryApiAdminController extends EscolaLmsBaseController implements CourseAccessEnquiryApiAdminSwagger
{
    private CourseAccessEnquiryServiceContract $service;

    public function __construct(CourseAccessEnquiryServiceContract $service)
    {
        $this->service = $service;
    }

    public function list(AdminListCourseAccessEnquiryRequest $request): JsonResponse
    {
        $result = $this->service->findAll(
            $request->getCriteriaDto(),
            $request->getPaginationDto(),
            $request->getOrderDto(),
            $request->get('per_page', 20)
        );

        return $this->sendResponseForResource(CourseAccessEnquiryResource::collection($result));
    }

    public function delete(AdminDeleteCourseAccessEnquiryRequest $request): JsonResponse
    {
        $this->service->delete($request->getCourseAccessEnquiry());

        return $this->sendSuccess(__('Course access enquiry deleted successfully.'));
    }

    public function approve(AdminApproveCourseAccessEnquiry $request): JsonResponse
    {
        $this->service->approve($request->getCourseAccessEnquiry());

        return $this->sendSuccess(__('Course access enquiry approved successfully.'));
    }
}
