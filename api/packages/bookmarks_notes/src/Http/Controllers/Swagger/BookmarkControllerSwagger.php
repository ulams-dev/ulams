<?php

namespace EscolaLms\Bookmarks\Http\Controllers\Swagger;

use EscolaLms\Bookmarks\Http\Requests\CreateBookmarkRequest;
use EscolaLms\Bookmarks\Http\Requests\DeleteBookmarkRequest;
use EscolaLms\Bookmarks\Http\Requests\ListBookmarkRequest;
use EscolaLms\Bookmarks\Http\Requests\UpdateBookmarkRequest;
use Illuminate\Http\JsonResponse;

interface BookmarkControllerSwagger
{

    /**
     * @OA\Post(
     *      path="/api/bookmarks",
     *      summary="Store a newly created bookmark",
     *      tags={"Bookmarks"},
     *      description="Store Bookmark",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/BookmarkCreateRequest")
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
     *                      property="data",
     *                      ref="#/components/schemas/BookmarkResource"
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
    public function create(CreateBookmarkRequest $request): JsonResponse;


    /**
     * @OA\Patch(
     *      path="/api/bookmarks/{id}",
     *      summary="Update bookmark",
     *      tags={"Bookmarks"},
     *      description="Store Bookmark",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="ID of bookmark",
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
     *              @OA\Schema(ref="#/components/schemas/BookmarkCreateRequest")
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
     *                      property="data",
     *                      ref="#/components/schemas/BookmarkResource"
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
    public function update(UpdateBookmarkRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *      path="/api/bookmarks/{id}",
     *      summary="Remove the specified bookmark",
     *      tags={"Bookmarks"},
     *      description="Delete Bookmarks",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="ID of bookmark",
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
    public function delete(DeleteBookmarkRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/bookmarks",
     *      summary="Get a listing of the bookmarks",
     *      tags={"Bookmarks"},
     *      description="Get all bookmarks",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="order_by",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"created_at", "id", "value"}
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
     *          name="has_value",
     *          description="Has value",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="bool",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="bookmarkable_id",
     *          description="Bookmarkalbe ID",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="number",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="bookmarkable_ids[]",
     *          description="An array of bookmark IDs.",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="array", @OA\Items(type="number")),
     *      ),
     *      @OA\Parameter(
     *          name="bookmarkable_type",
     *          description="Bookmarkalbe type",
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
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/BookmarkResource")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function findAll(ListBookmarkRequest $request): JsonResponse;
}
