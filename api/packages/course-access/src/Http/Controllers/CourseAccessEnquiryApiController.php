<?php

namespace Ulams\CourseAccess\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\CourseAccess\Exceptions\EnquiryAlreadyExistsException;
use Ulams\CourseAccess\Http\Controllers\Swagger\CourseAccessEnquiryApiSwagger;
use Ulams\CourseAccess\Http\Requests\CreateCourseAccessEnquiryApiRequest;
use Ulams\CourseAccess\Http\Requests\DeleteCourseAccessEnquiryRequest;
use Ulams\CourseAccess\Http\Requests\ListCourseAccessEnquiryRequest;
use Ulams\CourseAccess\Http\Resources\CourseAccessEnquiryResource;
use Ulams\CourseAccess\Services\Contracts\CourseAccessEnquiryServiceContract;
use Illuminate\Http\JsonResponse;

class CourseAccessEnquiryApiController extends UlamsBaseController implements CourseAccessEnquiryApiSwagger
{
    private CourseAccessEnquiryServiceContract $service;

    public function __construct(CourseAccessEnquiryServiceContract $service)
    {
        $this->service = $service;
    }

    public function list(ListCourseAccessEnquiryRequest $request): JsonResponse
    {
        $result = $this->service->findByUser($request->getCriteriaDto(), $request->getPaginationDto(), auth()->id());

        return $this->sendResponseForResource(CourseAccessEnquiryResource::collection($result));
    }

    public function create(CreateCourseAccessEnquiryApiRequest $request): JsonResponse
    {
        try {
            $result = $this->service->create($request->getCreateCourseAccessEnquiryDto());
        } catch (EnquiryAlreadyExistsException $e) {
            return $this->sendError($e->getMessage(), $e->getCode());
        }

        return $this->sendResponseForResource(CourseAccessEnquiryResource::make($result), __('Course access enquiry created successfully.'));
    }

    public function delete(DeleteCourseAccessEnquiryRequest $request): JsonResponse
    {
        $this->service->delete($request->getCourseAccessEnquiry());

        return $this->sendSuccess(__('Course access enquiry deleted successfully.'));
    }
}
