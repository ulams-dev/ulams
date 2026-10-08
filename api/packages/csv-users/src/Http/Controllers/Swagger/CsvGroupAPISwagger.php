<?php

namespace EscolaLms\CsvUsers\Http\Controllers\Swagger;

use EscolaLms\CsvUsers\Http\Requests\ExportUserGroupToCsvAPIRequest;
use EscolaLms\CsvUsers\Http\Requests\ImportUserGroupFromCsvAPIRequest;

interface CsvGroupAPISwagger
{
    /**
     * @OA\Get(
     *      tags={"Admin CSV"},
     *      path="/api/admin/csv/groups/group_id",
     *      description="Exports users to csv",
     *      security={
     *          {"passport": {}},
     *      },
     *     @OA\Parameter(
     *          name="format",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *          description="exported file format"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/csv",
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
    public function export(ExportUserGroupToCsvAPIRequest $request);

    /**
     * @OA\Post(
     *      tags={"Admin CSV"},
     *      path="/api/admin/csv/groups",
     *      description="Imports group from csv",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(
     *                      property="file",
     *                      type="file",
     *                      format="file",
     *                  ),
     *                  @OA\Property(
     *                      property="return_url",
     *                      type="string",
     *                      example="https://escolalms.com/set-password",
     *                      description="Address where the new user set the password"
     *                 ),
     *              )
     *          )
     *      ),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
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
    public function import(ImportUserGroupFromCsvAPIRequest $request);
}
