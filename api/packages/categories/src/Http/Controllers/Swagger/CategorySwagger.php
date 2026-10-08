<?php

namespace EscolaLms\Categories\Http\Controllers\Swagger;

use EscolaLms\Categories\Http\Requests\CategoryCreateRequest;
use EscolaLms\Categories\Http\Requests\CategoryDeleteRequest;
use EscolaLms\Categories\Http\Requests\CategoryListRequest;
use EscolaLms\Categories\Http\Requests\CategoryReadRequest;
use EscolaLms\Categories\Http\Requests\CategorySortRequest;
use EscolaLms\Categories\Http\Requests\CategoryUpdateRequest;
use EscolaLms\Categories\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface CategorySwagger
{
    /**
     * @OA\Get(
     *      tags={"Categories"},
     *      path="/api/categories",
     *      description="Get Categories",
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
    public function index(CategoryListRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      tags={"Categories"},
     *      path="/api/categories/tree",
     *      description="Get Categories Tree",
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
    public function tree(CategoryListRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *     tags={"Categories"},
     *      path="/api/admin/categories/{id}",
     *      description="Get single Categories",
     *      @OA\Parameter(
     *          name="id",
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
     *          response=422,
     *          description="Bad request",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          )
     *      )
     *   )
     */
    public function show(int $id, CategoryReadRequest $categoryReadRequest): JsonResponse;

    /**
     * @OA\Post(
     *     tags={"Categories"},
     *     path="/api/admin/categories",
     *     summary="Category create",
     *     description="Create single Categories",
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 example="Dokumentacja",
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *             ),
     *             @OA\Property(
     *                 property="icon_class",
     *                 type="string",
     *                 example="fa-business-time",
     *             ),
     *             @OA\Property(
     *                 property="is_active",
     *                 type="bool",
     *                 example="true",
     *             ),
     *             @OA\Property(
     *                 property="parent_id",
     *                 type="?integer",
     *                 example="null",
     *             ),
     *             @OA\Property(
     *                 property="order",
     *                 type="number",
     *                 example="1"
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
    public function create(CategoryCreateRequest $categoryCreateRequest): JsonResponse;

    /**
     * @OA\Post(
     *     tags={"Categories"},
     *     path="/api/admin/categories/{id}",
     *     summary="Update category",
     *     description="Update single Categories",
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *       name="id",
     *       required=true,
     *       in="path",
     *       @OA\Schema(
     *           type="integer",
     *        ),
     *      ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 example="Dokumentacja",
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *             ),
     *             @OA\Property(
     *                 property="icon_class",
     *                 type="string",
     *                 example="fa-business-time",
     *             ),
     *             @OA\Property(
     *                 property="is_active",
     *                 type="bool",
     *                 example="true",
     *             ),
     *             @OA\Property(
     *                 property="parent_id",
     *                 type="?integer",
     *                 example="null",
     *             ),
     *             @OA\Property(
     *                 property="order",
     *                 type="number",
     *                 example="1"
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
    public function update(int $id, CategoryUpdateRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *     tags={"Categories"},
     *     security={
     *         {"passport": {}},
     *     },
     *     path="/api/admin/categories/{id}",
     *     summary="Destroy category",
     *     description="Destroy the specified category",
     *     @OA\Parameter(
     *      name="id",
     *      required=true,
     *      in="path",
     *      @OA\Schema(
     *          type="integer",
     *       ),
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
    public function delete(int $id, CategoryDeleteRequest $categoryDeleteRequest): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/categories/sort",
     *      tags={"Categories"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/AdminCategorySortRequest")
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
     *                  )
     *              )
     *          )
     *      )
     * )
     */
    public function sort(CategorySortRequest $request): JsonResponse;
}
