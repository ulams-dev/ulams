<?php

namespace EscolaLms\TopicTypeGift\Http\Controllers\Swagger;

use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminListGiftQuizRequest;
use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminReadGiftQuizRequest;
use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminUpdateGiftQuizRequest;
use Illuminate\Http\JsonResponse;

interface GiftQuizApiAdminSwagger
{
    /**
     * @OA\Get(
     *     path="/api/admin/gift-quizes",
     *     summary="List gift quizes of a course (for report filter select)",
     *      tags={"Gift Quiz"},
     *     security={
     *          {"passport": {}},
     *      },
     *     @OA\Parameter(
     *          name="course_id",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="integer",
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
     *                      property="data",
     *                      type="array",
     *                      @OA\Items(ref="#/components/schemas/GiftQuizSimpleResource")
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
    public function index(AdminListGiftQuizRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/admin/gift-quizes/{id}",
     *     summary="Get gift quiz by id",
     *      tags={"Gift Quiz"},
     *     security={
     *          {"passport": {}},
     *      },
     *     @OA\Parameter(
     *          name="id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer",
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
     *                      property="data",
     *                      @OA\Schema(ref="#/components/schemas/AdminGiftQuizResource")
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
    public function read(AdminReadGiftQuizRequest $request): JsonResponse;

    /**
     * @OA\Put(
     *     path="/api/admin/gift-quizes/{id}",
     *     summary="Update gift quiz by id",
     *      tags={"Gift Quiz"},
     *     security={
     *          {"passport": {}},
     *      },
     *     @OA\Parameter(
     *          name="id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/AdminUpdateGiftQuizRequest")
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
     *                      property="data",
     *                      @OA\Schema(ref="#/components/schemas/AdminGiftQuizResource")
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
    public function update(AdminUpdateGiftQuizRequest $request): JsonResponse;
}
