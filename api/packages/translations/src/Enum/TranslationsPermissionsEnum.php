<?php

namespace EscolaLms\Translations\Enum;

use EscolaLms\Core\Enums\BasicEnum;

class TranslationsPermissionsEnum extends BasicEnum
{
    public const TRANSLATION_LIST = 'translation_list';
    public const TRANSLATION_CREATE = 'translation_create';
    public const TRANSLATION_READ = 'translation_read';
    public const TRANSLATION_UPDATE = 'translation_update';
    public const TRANSLATION_DELETE = 'translation_delete';
    public const TRANSLATION_LIST_READONLY = 'translation_list_readonly';
}
