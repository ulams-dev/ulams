<?php

namespace EscolaLms\BulkNotifications\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;


/**
 * @OA\Schema(
 *      schema="BulkNotificationResource",
 *      required={"id", "channel", "sections", "users"},
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="channel",
 *          description="channel",
 *          type="string"
 *      ),
 *      @OA\Property(
 *           property="sections",
 *           type="array",
 *           @OA\Items(ref="#/components/schemas/BulkNotificationSectionResource")
 *       ),
 *       @OA\Property(
 *           property="users",
 *           description="users",
 *           type="array",
 *           @OA\Items(
 *               type="integer"
 *            )
 *       ),
 * )
 *
 */
class BulkNotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'channel' => $this->resource->channel,
            'sections' => BulkNotificationSectionResource::collection($this->resource->sections),
            'users' => $this->resource->users->pluck('id')
        ];
    }
}
