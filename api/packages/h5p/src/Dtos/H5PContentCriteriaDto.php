<?php

namespace Ulams\H5P\Dtos;

use Illuminate\Http\Request;
use Ulams\Core\Dtos\Contracts\InstantiateFromRequest;
use Ulams\H5P\Enums\H5PPermissionsEnum;

class H5PContentCriteriaDto implements InstantiateFromRequest
{
    public ?string $title;
    public ?string $mainLibrary;
    /** Restrict to contents of this LMS user id (h5p.contents.user_id). */
    public ?string $userId;

    public function __construct(?string $title = null, ?string $mainLibrary = null, ?string $userId = null)
    {
        $this->title = $title;
        $this->mainLibrary = $mainLibrary;
        $this->userId = $userId;
    }

    public static function instantiateFromRequest(Request $request): self
    {
        $user = $request->user();
        $userId = null;
        if ($user && $user->can(H5PPermissionsEnum::H5P_LIST)) {
            $userId = $request->filled('author_id') ? (string) $request->input('author_id') : null;
        } elseif ($user) {
            // h5p_author_list only: own content
            $userId = (string) $user->getKey();
        }

        return new self(
            $request->filled('title') ? (string) $request->input('title') : null,
            $request->filled('main_library') ? (string) $request->input('main_library') : null,
            $userId,
        );
    }
}
