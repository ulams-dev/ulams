<?php

namespace EscolaLms\Dictionaries\Http\Controllers\Swagger;

use EscolaLms\Dictionaries\Http\Requests\DictionaryWord\ListDictionaryWordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface DictionaryWordApiControllerSwagger
{
    /**
     * @OA\Get(
     *      path="/api/dictionaries/{slug}/words",
     *      summary="Get a listing of the Dictionary Word",
     *      tags={"Dictionaries"},
     *      @OA\Parameter(
     *           name="slug",
     *           required=true,
     *           in="path",
     *           @OA\Schema(
     *               type="string",
     *           ),
     *       ),
     *      @OA\Parameter(
     *          name="word",
     *          description="word %ILIKE%",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="word_start",
     *          description="word ILIKE%",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *           name="category_ids[]",
     *           required=false,
     *           in="query",
     *           @OA\Schema(
     *               type="array",
     *               @OA\Items(type="number")
     *           ),
     *       ),
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
     * @OA\Get(
     *      path="/api/dictionaries/{slug}/words/{id}",
     *      summary="Display the specified Dictionary Word",
     *      tags={"Dictionaries"},
     *      @OA\Parameter(
     *          name="slug",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
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
     *      ),
     *      @OA\Response(
     *          response=429,
     *          description="Too Many Requests",
     *      )
     * )
     */
    public function show(Request $request, string $slug, int $id): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/dictionaries/{slug}/words/categories",
     *      summary="Get the categories used in the dictionary for a given slug",
     *      tags={"Dictionaries"},
     *      @OA\Parameter(
     *           name="slug",
     *           required=true,
     *           in="path",
     *           @OA\Schema(
     *               type="string",
     *           ),
     *       ),
     *      @OA\Parameter(
     *          name="word",
     *          description="word %ILIKE%",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="word_start",
     *          description="word ILIKE%",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *           name="category_ids[]",
     *           required=false,
     *           in="query",
     *           @OA\Schema(
     *               type="array",
     *               @OA\Items(type="number")
     *           ),
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
     *                      @OA\Items(ref="#/components/schemas/DictionaryWordCategorySimpleResource")
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
    public function categories(ListDictionaryWordRequest $request): JsonResponse;
}
