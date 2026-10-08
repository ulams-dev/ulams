<?php

namespace EscolaLms\Templates\Http\Controllers\Contracts;

use EscolaLms\Templates\Http\Requests\EventTriggerRequest;
use Illuminate\Http\JsonResponse;

interface EventAdminApiContract
{
    /**
     * @OA\Post(
     *     path="/api/admin/events/trigger-manually/{id}",
     *     summary="Manually triggered event for users",
     *     tags={"Templates"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Template id",
     *         in="path",
     *         name="id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="users",
     *                  type="array",
     *                  description="Ids of users",
     *                  @OA\Items(
     *                      type="integer",
     *                  )
     *              )
     *          )
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="Event dispatched successfully",
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *      ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=422,
     *          description="one of the parameters has invalid format",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     */
    public function triggerManually(EventTriggerRequest $request): JsonResponse;
}
