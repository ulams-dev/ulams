<?php

namespace Ulams\H5P\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Ulams\H5P\Http\Requests\DeleteUnusedH5PContentRequest;
use Ulams\H5P\Http\Requests\ListH5PContentRequest;

/**
 * Laravel side of H5P. Creating, editing, playing, uploading, exporting and
 * library administration are served by the H5P service under /h5p/* on the
 * same host (see api/h5p/README.md).
 */
interface H5PContentAdminApiSwagger
{
    /**
     * @OA\Get(
     *      path="/api/admin/h5p/contents",
     *      summary="List H5P contents with the number of topics using each",
     *      tags={"Admin H5P"},
     *      description="Needs h5p_list, or h5p_author_list (then only own contents are listed).",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Parameter(name="title", in="query", required=false, @OA\Schema(type="string")),
     *      @OA\Parameter(name="main_library", in="query", required=false, description="machine name, e.g. H5P.MultiChoice", @OA\Schema(type="string")),
     *      @OA\Parameter(name="author_id", in="query", required=false, description="only with h5p_list", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer")),
     *      @OA\Parameter(name="per_page", in="query", required=false, description="0 returns all rows without pagination", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="order_by", in="query", required=false, @OA\Schema(type="string", enum={"id", "title", "main_library", "library", "user_id", "created_at", "updated_at", "count_h5p"})),
     *      @OA\Parameter(name="order", in="query", required=false, @OA\Schema(type="string", enum={"ASC", "DESC"})),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(property="success", type="boolean"),
     *                  @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/H5PContentListItem")),
     *                  @OA\Property(property="meta", type="object"),
     *                  @OA\Property(property="message", type="string")
     *              )
     *          )
     *      ),
     *      @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(ListH5PContentRequest $request): JsonResponse;

    /**
     * @OA\Delete(
     *      path="/api/admin/h5p/unused",
     *      summary="Delete H5P contents that no topic uses",
     *      tags={"Admin H5P"},
     *      description="Needs h5p_delete. Deletes through the H5P service (content files, user states, results).",
     *      security={
     *          {"passport": {}},
     *      },
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(property="success", type="boolean"),
     *                  @OA\Property(
     *                      property="data",
     *                      type="object",
     *                      @OA\Property(property="ids", type="array", @OA\Items(type="integer")),
     *                      @OA\Property(property="failed", type="array", @OA\Items(type="object",
     *                          @OA\Property(property="id", type="integer"),
     *                          @OA\Property(property="message", type="string")
     *                      ))
     *                  ),
     *                  @OA\Property(property="message", type="string")
     *              )
     *          )
     *      ),
     *      @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function deleteUnused(DeleteUnusedH5PContentRequest $request): JsonResponse;
}
