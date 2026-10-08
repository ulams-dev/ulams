<?php

namespace EscolaLms\CoursesImportExport\Http\Controllers\Swagger;

use EscolaLms\CoursesImportExport\Http\Requests\CloneCourseAPIRequest;
use EscolaLms\CoursesImportExport\Http\Requests\CourseImportAPIRequest;
use EscolaLms\CoursesImportExport\Http\Requests\GetCourseExportAPIRequest;
use Illuminate\Http\JsonResponse;

interface CourseExportImportAPISwagger
{
    /**
     * @OA\Get(
     *      tags={"Admin Courses"},
     *      path="/api/admin/courses/{id}/export",
     *      description="Exports course to ZIP package ",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="number",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Bad request",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     *   )
     */
    public function export(int $course_id, GetCourseExportAPIRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      tags={"Admin Courses"},
     *      path="/api/admin/courses/zip/import",
     *      description="Imports course from ZIP package ",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  type="object",
     *                  required={"file"},
     *                  @OA\Property(
     *                      property="file",
     *                      type="file",
     *                      format="file",
     *                  )
     *              )
     *          )
     *      ),
     *     @OA\Response(
     *          response=201,
     *          description="Course imported successfully",
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *      ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     */
    public function import(CourseImportAPIRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      tags={"Admin Courses"},
     *      path="/api/admin/courses/{id}/clone",
     *      description="Course cloning",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="number",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Bad request",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     *   )
     */
    public function clone(int $course_id, CloneCourseAPIRequest $request): JsonResponse;
}
