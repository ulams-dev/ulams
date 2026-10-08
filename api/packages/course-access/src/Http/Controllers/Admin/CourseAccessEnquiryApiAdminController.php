<?php

namespace Ulams\CourseAccess\Http\Controllers\Admin;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\CourseAccess\Http\Controllers\Admin\Swagger\CourseAccessEnquiryApiAdminSwagger;
use Ulams\CourseAccess\Http\Requests\Admin\AdminApproveCourseAccessEnquiry;
use Ulams\CourseAccess\Http\Requests\Admin\AdminDeleteCourseAccessEnquiryRequest;
use Ulams\CourseAccess\Http\Requests\Admin\AdminListCourseAccessEnquiryRequest;
use Ulams\CourseAccess\Http\Resources\CourseAccessEnquiryResource;
use Ulams\CourseAccess\Services\Contracts\CourseAccessEnquiryServiceContract;
use Illuminate\Http\JsonResponse;

class CourseAccessEnquiryApiAdminController extends UlamsBaseController implements CourseAccessEnquiryApiAdminSwagger
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
