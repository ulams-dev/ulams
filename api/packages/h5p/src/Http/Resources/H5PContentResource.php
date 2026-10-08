<?php

namespace Ulams\H5P\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\H5P\Models\H5PContent;

/**
 * @OA\Schema(
 *      schema="H5PContentListItem",
 *      @OA\Property(property="id", type="integer", example=12),
 *      @OA\Property(property="title", type="string", example="Capitals of Europe"),
 *      @OA\Property(property="library", type="string", example="H5P.MultiChoice 1.16"),
 *      @OA\Property(property="main_library", type="string", example="H5P.MultiChoice"),
 *      @OA\Property(property="library_version", type="string", example="1.16"),
 *      @OA\Property(property="user_id", type="integer", nullable=true, example=1, description="LMS user id of the creator (string for non-numeric ids)"),
 *      @OA\Property(property="created_at", type="string", format="date-time"),
 *      @OA\Property(property="updated_at", type="string", format="date-time"),
 *      @OA\Property(property="count_h5p", type="integer", example=1, description="Number of H5P topics using this content"),
 * )
 *
 * @mixin H5PContent
 */
class H5PContentResource extends JsonResource
{
    public function toArray($request): array
    {
        $userId = $this->resource->user_id;

        return [
            'id' => (int) $this->resource->id,
            'title' => $this->resource->title,
            'library' => $this->resource->library,
            'main_library' => $this->resource->main_library,
            'library_version' => $this->resource->library_version,
            'user_id' => is_string($userId) && ctype_digit($userId) ? (int) $userId : $userId,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'count_h5p' => (int) ($this->resource->count_h5p ?? 0),
        ];
    }
}
