<?php

namespace EscolaLms\Tags\Http\Controllers\Swagger;

use EscolaLms\Tags\Http\Request\TagInsertRequest;
use EscolaLms\Tags\Http\Request\TagRemoveRequest;
use EscolaLms\Tags\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface TagsApiSwagger
{
    /**
     * @OA\Get(
     *      tags={"Tags"},
     *      path="/api/tags",
     *      description="Display a listing of the Tag",
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
    public function index(Request $request): JsonResponse;

    /**
     * @OA\Get(
     *      tags={"Tags"},
     *      path="/api/tags/unique",
     *      description="Display the unique Tags",
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
    public function unique(Request $request): JsonResponse;

    /**
     * @OA\Post(
     *      tags={"Tags"},
     *      path="/api/admin/tags/create",
     *      description="Create multiple Tags",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="model_type",
     *                  type="string",
     *                  example="Category",
     *              ),
     *              @OA\Property(
     *                  property="model_id",
     *                  type="integer",
     *                  example="1",
     *              ),
     *              @OA\Property(
     *                  property="tags",
     *                  type="array",
     *                  @OA\Items(
     *                      @OA\Property(
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(
     *                                property="title",
     *                                type="string",
     *                                example="Nowości"
     *                             ),
     *                         ),
     *                      ),
     *                  ),
     *              ),
     *          )
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
    public function create(TagInsertRequest $tagInsertRequest): JsonResponse;

    /**
     * @OA\Delete(
     *     tags={"Tags"},
     *     security={
     *         {"passport": {}},
     *     },
     *     path="/api/admin/tags",
     *     summary="Destroy Tags",
     *     description="Destroy Tags",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="tags",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(
     *                        type="integer",
     *                        example="1"
     *                     ),
     *                 ),
     *             ),
     *         )
     *     ),
     *     @OA\Response(
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
     * )
     */
    public function destroy(TagRemoveRequest $tagRemoveRequest): JsonResponse;

    /**
     * @OA\Get(
     *      tags={"Tags"},
     *      security={
     *          {"passport": {}},
     *      },
     *      path="/api/admin/tags/{tag}",
     *      description="Display the specified Tag",
     *      @OA\Parameter(
     *          name="tag",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer",
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
     *          response=404,
     *          description="Not found",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     *   )
     */
    public function show(Tag $tag, Request $request): JsonResponse;
}
