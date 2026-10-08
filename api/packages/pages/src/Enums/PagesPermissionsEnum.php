<?php

namespace EscolaLms\Pages\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class PagesPermissionsEnum extends BasicEnum
{
    const PAGE_LIST = 'page_list';
    const PAGE_READ = 'page_read';
    const PAGE_CREATE = 'page_create';
    const PAGE_DELETE = 'page_delete';
    const PAGE_UPDATE = 'page_update';
}
