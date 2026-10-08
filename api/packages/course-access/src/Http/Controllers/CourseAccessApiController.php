<?php

namespace Ulams\CourseAccess\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\CourseAccess\Http\Controllers\Swagger\CourseAccessApiSwagger;
use Ulams\CourseAccess\Services\Contracts\CourseAccessServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CourseAccessApiController extends UlamsBaseController implements CourseAccessApiSwagger
{
    private CourseAccessServiceContract $courseAccessService;

    public function __construct(CourseAccessServiceContract $courseAccessService)
    {
        $this->courseAccessService = $courseAccessService;
    }

    public function getMyCourseIds(Request $request): JsonResponse
    {
        return $this->sendResponse([
            'ids' => $this->courseAccessService->getUserCourseIds(auth()->id(), $request->boolean('active')),
        ]);
    }
}
