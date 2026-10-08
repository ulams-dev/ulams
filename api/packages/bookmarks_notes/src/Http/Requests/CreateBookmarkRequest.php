<?php

namespace Ulams\Bookmarks\Http\Requests;

use Ulams\Bookmarks\Dtos\CreateBookmarkDto;
use Ulams\Bookmarks\Models\Bookmark;
use Illuminate\Support\Facades\Gate;


/**
 * @OA\Schema(
 *      schema="BookmarkCreateRequest",
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
 * )
 *
 */
class CreateBookmarkRequest extends BookmarkRequest
{
    public function authorize(): bool
    {
        return Gate::allows('createOwn', Bookmark::class);
    }

    public function rules(): array
    {
        return [
            'value' => ['nullable', 'string'],
            'bookmarkable_id' => ['required', 'integer'],
            'bookmarkable_type' => ['required', 'string'],
        ];
    }

    public function toDto(): CreateBookmarkDto
    {
        return new CreateBookmarkDto(
            $this->input('value'),
            $this->input('bookmarkable_type'),
            $this->input('bookmarkable_id'),
        );
    }
}
