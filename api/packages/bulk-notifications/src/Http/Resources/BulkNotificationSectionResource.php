<?php

namespace EscolaLms\BulkNotifications\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;


/**
 * @OA\Schema(
 *      schema="BulkNotificationSectionResource",
 *      required={"id", "key", "value"},
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="key",
 *          description="key",
 *          type="string"
 *      ),
 *     @OA\Property(
 *          property="value",
 *          description="value",
 *          type="string"
 *      ),
 * )
 *
 */
class BulkNotificationSectionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'key' => $this->resource->key,
            'value' => $this->resource->value,
        ];
    }
}
