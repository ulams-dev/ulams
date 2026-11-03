<?php

namespace Ulams\Cart\Http\Swagger\Admin;

use Ulams\Cart\Http\Requests\Admin\ProductableAttachRequest;
use Ulams\Cart\Http\Requests\Admin\ProductableDetachRequest;
use Ulams\Cart\Http\Requests\Admin\ProductableListRequest;
use Ulams\Cart\Http\Requests\Admin\ProductableProductRequest;
use Ulams\Cart\Http\Requests\Admin\ProductableRegisteredListRequest;
use Illuminate\Http\JsonResponse;

interface ProductableAdminSwagger
{
    /**
     * @OA\Get(
     *      path="/api/admin/productables/", operationId="admin.productables.get",
     *      description="Get list of all productables",
     *      tags={"Admin Product"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *              ),
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
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
    public function index(ProductableListRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/productables/attach", operationId="admin.productables.attach.post",
     *      description="Attach productable to user",
     *      tags={"Admin Products"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/json",
     *              @OA\Schema(
     *                  type="object",
     *                  required={"productable_id", "productable_type", "user_id"},
     *                  @OA\Property(
     *                      property="productable_id",
     *                      type="integer",
     *                  ),
     *                  @OA\Property(
     *                      property="productable_type",
     *                      type="string",
     *                  ),
     *                  @OA\Property(
     *                      property="user_id",
     *                      type="integer",
     *                  ),
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
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
    public function attach(ProductableAttachRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/productables/detach", operationId="admin.productables.detach.post",
     *      description="Detach product from user",
     *      tags={"Admin Product"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/json",
     *              @OA\Schema(
     *                  type="object",
     *                  required={"productable_id", "productable_type", "user_id"},
     *                  @OA\Property(
     *                      property="productable_id",
     *                      type="integer",
     *                  ),
     *                  @OA\Property(
     *                      property="productable_type",
     *                      type="string",
     *                  ),
     *                  @OA\Property(
     *                      property="user_id",
     *                      type="integer",
     *                  ),
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
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
    public function detach(ProductableDetachRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/admin/productables/registered", operationId="admin.productables.registered.get",
     *      description="List of registered Productable types",
     *      tags={"Admin Product"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="data",
     *                  type="array"
     *              ),
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
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
    public function registered(ProductableRegisteredListRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/admin/productables/product", operationId="admin.productables.product.get",
     *      description="Get single product for this productable (if it exists)",
     *      tags={"Admin Product"},
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="productable_type",
     *          description="Productable class",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="productable_id",
     *          description="Productable id",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/ApiEnvelope"),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Schema(ref="#/components/schemas/Product")
     *              ),
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
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
    public function product(ProductableProductRequest $request): JsonResponse;
}
