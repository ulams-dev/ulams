<?php

namespace EscolaLms\CsvUsers\Http\Controllers\Swagger;

use EscolaLms\CsvUsers\Http\Requests\ExportUsersToCsvAPIRequest;
use EscolaLms\CsvUsers\Http\Requests\ImportUsersFromCsvAPIRequest;

interface CsvUserAPISwagger
{
    /**
     * @OA\Get(
     *      tags={"Admin CSV"},
     *      path="/api/admin/csv/users",
     *      description="Exports users to csv",
     *      security={
     *          {"passport": {}},
     *      },
     *     @OA\Parameter(
     *          name="search",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *          description="will search through first_name, last_name and email"
     *      ),
     *     @OA\Parameter(
     *          name="role",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *          description="user role"
     *      ),
     *     @OA\Parameter(
     *          name="format",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *          description="exported file format"
     *      ),
     *     @OA\Parameter(
     *          name="status",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="boolean",
     *          ),
     *          description="will check if user is_active"
     *      ),
     *     @OA\Parameter(
     *          name="onboarding",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="boolean",
     *          ),
     *          description="will check if user completed onboarding"
     *      ),
     *     @OA\Parameter(
     *          name="from",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="datetime",
     *          ),
     *          description="users created after this date"
     *      ),
     *     @OA\Parameter(
     *          name="to",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="datetime",
     *          ),
     *          description="users created before this date"
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
    public function export(ExportUsersToCsvAPIRequest $request);

    /**
     * @OA\Post(
     *      tags={"Admin CSV"},
     *      path="/api/admin/csv/users",
     *      description="Imports users from csv",
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
    public function import(ImportUsersFromCsvAPIRequest $request);
}
