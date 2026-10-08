<?php

namespace EscolaLms\CourseAccess\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface CourseAccessApiSwagger
{
    /**
     * @OA\Get(
     *      path="/api/courses/my",
     *      summary="Get my course list IDs",
     *      tags={"Courses"},
     *      description="Get my course list IDs",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="active",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              enum={0,1},
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          ),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  @OA\Schema(
     *                      type="object",
     *                      @OA\Property(
     *                          property="id",
     *                          type="array",
     *                          @OA\Items(
     *                              type="integer",
     *                              description="The ID of the course"
     *                          )
     *                      ),
     *                  ),
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function getMyCourseIds(Request $request): JsonResponse;
}
