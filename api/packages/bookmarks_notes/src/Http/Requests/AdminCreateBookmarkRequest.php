<?php

namespace EscolaLms\Bookmarks\Http\Requests;

use EscolaLms\Bookmarks\Dtos\CreateBookmarkDto;
use EscolaLms\Bookmarks\Models\Bookmark;
use Illuminate\Support\Facades\Gate;


/**
 * @OA\Schema(
 *      schema="AdminBookmarkCreateRequest",
 *      required={"bookmarkable_id", "bookmarkable_type"},
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
 *           property="user_id",
 *           description="user_id",
 *           type="integer"
 *       ),
 * )
 *
 */
class AdminCreateBookmarkRequest extends BookmarkRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Bookmark::class);
    }

    public function rules(): array
    {
        return [
            'value' => ['nullable', 'string'],
            'bookmarkable_id' => ['required', 'integer'],
            'bookmarkable_type' => ['required', 'string'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function toDto(): CreateBookmarkDto
    {
        return CreateBookmarkDto::instantiateFromRequest($this);
    }
}
