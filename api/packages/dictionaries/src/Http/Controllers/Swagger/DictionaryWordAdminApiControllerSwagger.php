<?php

namespace EscolaLms\Dictionaries\Http\Controllers\Swagger;

use EscolaLms\Dictionaries\Http\Requests\DictionaryWord\Admin\CreateDictionaryWordRequest;
use EscolaLms\Dictionaries\Http\Requests\DictionaryWord\Admin\DeleteDictionaryWordRequest;
use EscolaLms\Dictionaries\Http\Requests\DictionaryWord\Admin\ImportDictionaryWordRequest;
use EscolaLms\Dictionaries\Http\Requests\DictionaryWord\Admin\ListDictionaryWordRequest;
use EscolaLms\Dictionaries\Http\Requests\DictionaryWord\Admin\ReadDictionaryWordRequest;
use EscolaLms\Dictionaries\Http\Requests\DictionaryWord\Admin\UpdateDictionaryWordRequest;
use Illuminate\Http\JsonResponse;

interface DictionaryWordAdminApiControllerSwagger
{
    /**
     * @OA\Get(
     *      path="/api/admin/dictionary-words",
     *      summary="Get a listing of the Dictionary Word",
     *      tags={"Admin Dictionary Words"},
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
     *          name="word",
     *          description="word %LIKE%",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *           name="dictionary_id",
     *           required=false,
     *           in="query",
     *           @OA\Schema(
     *               type="integer",
     *           ),
     *       ),
     *      @OA\Parameter(
     *          name="category_ids[]",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="array",
     *              @OA\Items(type="number")
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
     *                      @OA\Items(ref="#/components/schemas/DictionaryWordSimpleResource")
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
    public function index(ListDictionaryWordRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/dictionary-words",
     *      summary="Store a newly created dictionary word in storage",
     *      tags={"Admin Dictionary Words"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/CreateDictionaryWordRequest")
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
     *                      ref="#/components/schemas/DictionaryWordResource"
     *                  ),
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateDictionaryWordRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/admin/dictionary-words/{id}",
     *      summary="Display the specified Dictionary Word",
     *      tags={"Admin Dictionary Words"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Dictionary Word",
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
     *                      ref="#/components/schemas/DictionaryWordResource"
     *                  ),
     *              )
     *          )
     *      )
     * )
     */
    public function show(ReadDictionaryWordRequest $request): JsonResponse;

    /**
     * @OA\Put(
     *      path="/api/admin/dictionary-words/{id}",
     *      summary="Update the specified Dictionary Word in storage",
     *      tags={"Admin Dictionary Words"},
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
     *              @OA\Schema(ref="#/components/schemas/CreateDictionaryWordRequest"),
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
     *                      ref="#/components/schemas/DictionaryWordResource"
     *                  ),
     *              )
     *          )
     *      )
     * )
     */
    public function update(UpdateDictionaryWordRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *      path="/api/admin/dictionary-words/{id}",
     *      summary="Remove the specified Dictionary Word from storage",
     *      tags={"Admin Dictionary Words"},
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
    public function delete(DeleteDictionaryWordRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/dictionary-words/import",
     *      summary="Import dictionary words from file",
     *      tags={"Admin Dictionary Words"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(ref="#/components/schemas/ImportDictionaryWordRequest")
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
     *              )
     *          )
     *      )
     * )
     */
    public function import(ImportDictionaryWordRequest $request): JsonResponse;
}
