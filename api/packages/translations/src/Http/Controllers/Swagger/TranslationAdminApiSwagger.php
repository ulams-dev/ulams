<?php

namespace EscolaLms\Translations\Http\Controllers\Swagger;

use EscolaLms\Translations\Http\Requests\CreateLanguageLineRequest;
use EscolaLms\Translations\Http\Requests\DeleteLanguageLineRequest;
use EscolaLms\Translations\Http\Requests\ListLanguageLineRequest;
use EscolaLms\Translations\Http\Requests\ReadLanguageLineRequest;
use EscolaLms\Translations\Http\Requests\RetrieveTranslationRequest;
use EscolaLms\Translations\Http\Requests\UpdateLanguageLineRequest;
use Illuminate\Http\JsonResponse;

interface TranslationAdminApiSwagger
{
    /**
     * @OA\Get(
     *      path="/api/admin/translations",
     *      summary="Get a list of translations stored in the database",
     *      tags={"Admin Translations"},
     *      description="Get translations",
     *      security={
     *         {"passport": {}},
     *      },
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
     *          name="group",
     *          description="Group",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="key",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="public",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="bool"
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
    public function index(ListLanguageLineRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/translations",
     *      summary="Store a newly created translation",
     *      tags={"Admin Translations"},
     *      description="Store translation",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/language-line-save-request")
     *          ),
     *      ),
     *      @OA\Response(
     *          response=201,
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
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateLanguageLineRequest $request): JsonResponse;


    /**
     * @OA\Get(
     *      path="/api/admin/translations/{id}",
     *      summary="Display the specified translation",
     *      tags={"Admin Translations"},
     *      description="Get translation",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of translation",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *          ),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function show(ReadLanguageLineRequest $request): JsonResponse;

    /**
     * @OA\Put(
     *      path="/api/admin/translations/{id}",
     *      summary="Update the specified translation",
     *      tags={"Admin Translations"},
     *      description="Update translation",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of translation",
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
     *              @OA\Schema(ref="#/components/schemas/language-line-save-request")
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
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update(UpdateLanguageLineRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *      path="/api/admin/translations/{id}",
     *      summary="Remove the specified translation",
     *      tags={"Admin Translations"},
     *      description="Delete transalation",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of translation",
     *          @OA\Schema(
     *             type="integer",
     *         ),
     *          required=true,
     *          in="path"
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
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function delete(DeleteLanguageLineRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/translations/retrieve",
     *      summary="Retrieve translation",
     *      tags={"Admin Translations"},
     *      description="Retrieve translation",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/retrieve-translation-request")
     *          ),
     *      ),
     *      @OA\Response(
     *          response=201,
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
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     *
     *
     * @OA\Schema(
     *      schema="language-line-save-request",
     *      required={"text"},
     *      @OA\Property(
     *          property="group",
     *          description="group",
     *          type="string"
     *      ),
     *      @OA\Property(
     *          property="key",
     *          description="key",
     *          type="string"
     *      ),
     *      @OA\Property(
     *          property="text",
     *          description="text",
     *          type="object"
     *      ),
     * )
     *
     */
    public function translate(RetrieveTranslationRequest $request): JsonResponse;
}
