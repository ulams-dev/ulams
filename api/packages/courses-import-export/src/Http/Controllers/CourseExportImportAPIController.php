<?php

namespace Ulams\CoursesImportExport\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Courses\Http\Resources\CourseSimpleResource;
use Ulams\CoursesImportExport\Http\Controllers\Swagger\CourseExportImportAPISwagger;
use Ulams\CoursesImportExport\Http\Requests\CloneCourseAPIRequest;
use Ulams\CoursesImportExport\Http\Requests\CourseImportAPIRequest;
use Ulams\CoursesImportExport\Http\Requests\GetCourseExportAPIRequest;
use Ulams\CoursesImportExport\Services\Contracts\CloneCourseServiceContract;
use Ulams\CoursesImportExport\Services\Contracts\ExportImportServiceContract;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * SWAGGER_VERSION
 * This class should be parent class for other API controllers
 * Class AppBaseController.
 */
class CourseExportImportAPIController extends UlamsBaseController implements CourseExportImportAPISwagger
{
    protected ExportImportServiceContract $exportImportService;
    protected CloneCourseServiceContract $cloneCourseService;

    public function __construct(
        ExportImportServiceContract $exportImportService,
        CloneCourseServiceContract $cloneCourseService
    ) {
        $this->exportImportService = $exportImportService;
        $this->cloneCourseService = $cloneCourseService;
    }

    public function export(int $course_id, GetCourseExportAPIRequest $request): JsonResponse
    {
        $export = $this->exportImportService->export($course_id);

        return $this->sendResponse($export, __('Export created'));
    }

    public function import(CourseImportAPIRequest $request): JsonResponse
    {
        try {
            $course = $this->exportImportService->import($request->file('file'));

            return $this->sendResponseForResource(
                CourseSimpleResource::make($course),
                __('Course imported successfully')
            );
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    public function clone(int $course_id, CloneCourseAPIRequest $request): JsonResponse
    {
         $this->cloneCourseService->clone($request->getCourse());
         return $this->sendSuccess(__('Course cloning started. This may take a while.'));
    }
}
