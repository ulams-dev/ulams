<?php

namespace EscolaLms\CourseAccess\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\CourseAccess\Http\Controllers\Swagger\CourseAccessApiSwagger;
use EscolaLms\CourseAccess\Services\Contracts\CourseAccessServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CourseAccessApiController extends EscolaLmsBaseController implements CourseAccessApiSwagger
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
