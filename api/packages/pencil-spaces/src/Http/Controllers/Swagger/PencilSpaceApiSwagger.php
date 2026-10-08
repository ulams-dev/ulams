<?php

namespace EscolaLms\PencilSpaces\Http\Controllers\Swagger;

/**
 * @OA\Post(
 *      path="/api/pencil-spaces/login",
 *      summary="Generate a direct login link",
 *      tags={"Pencil Spaces"},
 *      security={
 *          {"passport": {}},
 *      },
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/LoginPencilSpaceRequest")
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
 *                      type="object",
 *                      @OA\Property(
 *                          property="url",
 *                          type="string"
 *                      )
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
interface PencilSpaceApiSwagger
{

}
