<?php

namespace EscolaLms\Dictionaries\Http\Controllers\Swagger;

use EscolaLms\Dictionaries\Http\Requests\Dictionary\CreateDictionaryRequest;
use EscolaLms\Dictionaries\Http\Requests\Dictionary\DeleteDictionaryRequest;
use EscolaLms\Dictionaries\Http\Requests\Dictionary\ListDictionaryRequest;
use EscolaLms\Dictionaries\Http\Requests\Dictionary\ReadDictionaryRequest;
use EscolaLms\Dictionaries\Http\Requests\Dictionary\UpdateDictionaryRequest;
use Illuminate\Http\JsonResponse;

interface DictionaryAdminApiControllerSwagger
{
    /**
     * @OA\Get(
     *      path="/api/admin/dictionaries",
     *      summary="Get a listing of the Dictionaries",
     *      tags={"Admin Dictionaries"},
     *      security={
     *         {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="order_by",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"id", "name", "created_at"}
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="order",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"ASC", "DESC"}
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="page",
     *          description="Pagination Page Number",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="number",
     *               default=1,
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          description="Pagination Per Page",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="number",
     *               default=15,
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="name",
     *          description="name %LIKE%",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(
     *                      property="success",
     *                      type="boolean"
     *                  ),
     *                  @OA\Property(
     *                      property="data",
     *                      type="array",
     *                      @OA\Items(ref="#/components/schemas/DictionaryResource")
     *                  ),
     *                  @OA\Property(
     *                      property="message",
     *                      type="string"
     *                  )
     *              )
     *          )
     *      )
     * )
     */
    public function index(ListDictionaryRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/dictionaries",
     *      summary="Store a newly created dictionary in storage",
     *      tags={"Admin Dictionaries"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/CreateDictionaryRequest")
     *          ),
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Successfull operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(
     *                      property="success",
     *                      type="boolean"
     *                  ),
     *                  @OA\Property(
     *                      property="message",
     *                      type="string"
     *                  ),
     *                  @OA\Property(
     *                      property="data",
     *                      ref="#/components/schemas/DictionaryResource"
     *                  ),
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateDictionaryRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/admin/dictionaries/{id}",
     *      summary="Display the specified Dictionary",
     *      tags={"Admin Dictionaries"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Dictionary",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successfull operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(
     *                      property="success",
     *                      type="boolean"
     *                  ),
     *                  @OA\Property(
     *                      property="message",
     *                      type="string"
     *                  ),
     *                  @OA\Property(
     *                      property="data",
     *                      ref="#/components/schemas/DictionaryResource"
     *                  ),
     *              )
     *          )
     *      )
     * )
     */
    public function show(ReadDictionaryRequest $request): JsonResponse;

    /**
     * @OA\Put(
     *      path="/api/admin/dictionaries/{id}",
     *      summary="Update the specified Dictionary in storage",
     *      tags={"Admin Dictionaries"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Dictionary",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/UpdateDictionaryRequest"),
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successfull operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(
     *                      property="success",
     *                      type="boolean"
     *                  ),
     *                  @OA\Property(
     *                      property="message",
     *                      type="string"
     *                  ),
     *                  @OA\Property(
     *                      property="data",
     *                      ref="#/components/schemas/DictionaryResource"
     *                  ),
     *              )
     *          )
     *      )
     * )
     */
    public function update(UpdateDictionaryRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *      path="/api/admin/dictionaries/{id}",
     *      summary="Remove the specified Dictionary from storage",
     *      tags={"Admin Dictionaries"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Dictionary",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successfull operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(
     *                      property="success",
     *                      type="boolean"
     *                  ),
     *                  @OA\Property(
     *                      property="message",
     *                      type="string"
     *                  )
     *              )
     *          )
     *      )
     * )
     */
    public function delete(DeleteDictionaryRequest $request): JsonResponse;
}
