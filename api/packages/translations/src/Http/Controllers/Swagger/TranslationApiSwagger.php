<?php

namespace EscolaLms\Translations\Http\Controllers\Swagger;

use EscolaLms\Translations\Http\Requests\PublicListLanguageLineRequest;
use Illuminate\Http\JsonResponse;

interface TranslationApiSwagger
{
    /**
     * @OA\Get(
     *      path="/api/translations",
     *      summary="Get a list of public translations",
     *      tags={"Translations"},
     *      description="Get public translations",
     *      @OA\Parameter(
     *          name="order_by",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
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
     *          name="group[]",
     *          description="An array of translation groups",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="array", @OA\Items(type="string")),
     *      ),
     *      @OA\Parameter(
     *          name="key[]",
     *          description="An array of translation keys",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="array", @OA\Items(type="string")),
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
     *                  type="array"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function index(PublicListLanguageLineRequest $request): JsonResponse;
}
