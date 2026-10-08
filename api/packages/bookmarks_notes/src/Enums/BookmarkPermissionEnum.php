<?php

namespace EscolaLms\Bookmarks\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class BookmarkPermissionEnum extends BasicEnum
{
    public const CREATE_BOOKMARK = 'bookmark_create';
    public const UPDATE_BOOKMARK = 'bookmark_update';
    public const DELETE_BOOKMARK = 'bookmark_delete';
    public const LIST_BOOKMARK = 'bookmark_list';

    public const CREATE_BOOKMARK_OWN = 'bookmark_create-own';
    public const UPDATE_BOOKMARK_OWN = 'bookmark_update-own';
    public const DELETE_BOOKMARK_OWN = 'bookmark_delete-own';
    public const LIST_BOOKMARK_OWN = 'bookmark_list-own';

    public static function studentPermissions(): array
    {
        return [
            self::CREATE_BOOKMARK_OWN,
            self::UPDATE_BOOKMARK_OWN,
            self::DELETE_BOOKMARK_OWN,
            self::LIST_BOOKMARK_OWN,
        ];
    }

    public static function adminPermissions(): array
    {
        return [
            self::CREATE_BOOKMARK,
            self::UPDATE_BOOKMARK,
            self::DELETE_BOOKMARK,
            self::LIST_BOOKMARK,
        ];
    }
}
