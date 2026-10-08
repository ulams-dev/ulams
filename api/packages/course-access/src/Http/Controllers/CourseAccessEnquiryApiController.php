<?php

namespace EscolaLms\CourseAccess\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\CourseAccess\Exceptions\EnquiryAlreadyExistsException;
use EscolaLms\CourseAccess\Http\Controllers\Swagger\CourseAccessEnquiryApiSwagger;
use EscolaLms\CourseAccess\Http\Requests\CreateCourseAccessEnquiryApiRequest;
use EscolaLms\CourseAccess\Http\Requests\DeleteCourseAccessEnquiryRequest;
use EscolaLms\CourseAccess\Http\Requests\ListCourseAccessEnquiryRequest;
use EscolaLms\CourseAccess\Http\Resources\CourseAccessEnquiryResource;
use EscolaLms\CourseAccess\Services\Contracts\CourseAccessEnquiryServiceContract;
use Illuminate\Http\JsonResponse;

class CourseAccessEnquiryApiController extends EscolaLmsBaseController implements CourseAccessEnquiryApiSwagger
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
