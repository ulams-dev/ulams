<?php

namespace EscolaLms\Bookmarks\Http\Resources;

use EscolaLms\Bookmarks\Models\Bookmark;
use Illuminate\Http\Resources\Json\JsonResource;


/**
 * @OA\Schema(
 *      schema="BookmarkResource",
 *      required={"value", "bookmarkable_id", "bookmarkable_type", "user"},
 *      @OA\Property(
 *          property="value",
 *          description="value",
 *          type="string"
 *      ),
 *     @OA\Property(
 *          property="bookmarkable_id",
 *          description="bookmarkable_id",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="bookmarkable_type",
 *          description="bookmarkable_type",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="user",
 *          ref="#/components/schemas/UserBookmarkResource"
 *      ),
 * )
 *
 * @mixin Bookmark
 */
class BookmarkResource extends JsonResource
{
    public function toArray($request): array
    {
        $resourceClass = ('\EscolaLms\Bookmarks\Http\Resources\\' . class_basename($this->bookmarkable_type) . 'Resource');

        return [
            'id' => $this->id,
            'value' => $this->value,
            'user' => UserResource::make($this->user),
            'bookmarkable' => class_exists($resourceClass) && class_exists($this->bookmarkable_type) && $this->bookmarkable ? $resourceClass::make($this->bookmarkable) : null,
            'bookmarkable_id' => $this->bookmarkable_id,
            'bookmarkable_type' => $this->bookmarkable_type,
        ];
    }
}
