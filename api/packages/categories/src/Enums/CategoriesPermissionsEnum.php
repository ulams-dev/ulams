<?php

namespace EscolaLms\Categories\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class CategoriesPermissionsEnum extends BasicEnum
{
    const CATEGORY_LIST = 'category_list';
    const CATEGORY_READ = 'category_read';
    const CATEGORY_UPDATE = 'category_update';
    const CATEGORY_CREATE = 'category_create';
    const CATEGORY_DELETE = 'category_delete';
}
