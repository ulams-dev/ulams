<?php

namespace EscolaLms\TopicTypeGift\Http\Controllers\Swagger;

use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminCreateGiftQuestionRequest;
use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminDeleteGiftQuestionRequest;
use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminExportGiftQuestionsRequest;
use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminImportGiftQuestionsRequest;
use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminSortGiftQuestionRequest;
use EscolaLms\TopicTypeGift\Http\Requests\Admin\AdminUpdateGiftQuestionRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

interface GiftQuestionApiAdminSwagger
{
    /**
     * @OA\Post(
     *      path="/api/admin/gift-questions",
     *      summary="Store a newly Gift Question",
     *      tags={"Admin Gift Question"},
     *      description="Store Gift Question",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/AdminGiftQuestionRequest")
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
     *                      @OA\Schema(ref="#/components/schemas/AdminGiftQuestionResource")
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
    public function create(AdminCreateGiftQuestionRequest $request): JsonResponse;

    /**
     * @OA\Put(
     *      path="/api/admin/gift-questions/{id}",
     *      summary="Update Gift Question",
     *      tags={"Admin Gift Question"},
     *      description="Update Gift Question",
     *      security={
     *          {"passport": {}},
     *      },
     *       @OA\Parameter(
     *          name="id",
     *          description="ID",
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
     *              @OA\Schema(ref="#/components/schemas/AdminGiftQuestionRequest")
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
     *                      @OA\Schema(ref="#/components/schemas/AdminGiftQuestionResource")
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
    public function update(AdminUpdateGiftQuestionRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *      path="/api/admin/gift-questions/{id}",
     *      summary="Remove the specified Gift Question",
     *      tags={"Admin Gift Question"},
     *      description="Delete Gift Question",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="ID",
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
    public function delete(AdminDeleteGiftQuestionRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/gift-questions/sort",
     *      summary="Sort Gift Questions",
     *      tags={"Admin Gift Question"},
     *      description="Sort Gift Questions",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(ref="#/components/schemas/AdminSortGiftQuestionRequest")
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
    public function sort(AdminSortGiftQuestionRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/admin/gift-questions/export",
     *     summary="Export Gift Questions",
     *     tags={"Admin Gift Question"},
     *     description="Export Gift Questions",
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *          name="topic_gift_quiz_id",
     *          required=false,
     *          in="path",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="category_ids[]",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="array",
     *              @OA\Items(
     *                  @OA\Schema(
     *                      type="integer"
     *                  ),
     *              ),
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="ids[]",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="array",
     *              @OA\Items(
     *                  @OA\Schema(
     *                      type="integer"
     *                  ),
     *              ),
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
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
    public function export(AdminExportGiftQuestionsRequest $request): BinaryFileResponse;

    /**
     * @OA\Post(
     *     path="/api/admin/gift-questions/import",
     *     summary="Import Gift Questions",
     *     tags={"Admin Gift Question"},
     *     description="Import Gift Questions",
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(ref="#/components/schemas/AdminImportGiftQuestionsRequest")
     *          ),
     *      ),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
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
    public function import(AdminImportGiftQuestionsRequest $request): JsonResponse;
}
