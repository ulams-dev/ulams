<?php

namespace Ulams\Tags\Enums;

use Ulams\Core\Enums\BasicEnum;

class TagsPermissionsEnum extends BasicEnum
{
    const TAGS_CREATE = 'tags_create';
    const TAGS_DELETE = 'tags_delete';
    const TAGS_LIST = 'tags_list';
    const TAGS_UPDATE = 'tags_update';
}
