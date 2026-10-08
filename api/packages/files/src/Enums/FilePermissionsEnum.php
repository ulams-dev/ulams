<?php

namespace EscolaLms\Files\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class FilePermissionsEnum extends BasicEnum
{
    const FILE_LIST = 'file_list';
    const FILE_LIST_SELF = 'file_list_self';
    const FILE_CREATE = 'file_create';
    const FILE_DELETE = 'file_delete';
    const FILE_UPDATE = 'file_update';
}
