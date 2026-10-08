<?php

namespace EscolaLms\Dictionaries\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class DictionariesPermissionEnum extends BasicEnum
{
    public const DICTIONARY_LIST = 'dictionary_list';
    public const DICTIONARY_CREATE = 'dictionary_create';
    public const DICTIONARY_READ = 'dictionary_read';
    public const DICTIONARY_UPDATE = 'dictionary_update';
    public const DICTIONARY_DELETE = 'dictionary_delete';

    public const DICTIONARY_WORD_LIST = 'dictionary-word_list';
    public const DICTIONARY_WORD_CREATE = 'dictionary-word_create';
    public const DICTIONARY_WORD_READ = 'dictionary-word_read';
    public const DICTIONARY_WORD_UPDATE = 'dictionary-word_update';
    public const DICTIONARY_WORD_DELETE = 'dictionary-word_delete';
    public const DICTIONARY_WORD_IMPORT = 'dictionary-word_import';

    public static function getAdminPermissions(): array
    {
        return [
            self::DICTIONARY_LIST,
            self::DICTIONARY_CREATE,
            self::DICTIONARY_READ,
            self::DICTIONARY_UPDATE,
            self::DICTIONARY_DELETE,
            self::DICTIONARY_WORD_LIST,
            self::DICTIONARY_WORD_CREATE,
            self::DICTIONARY_WORD_READ,
            self::DICTIONARY_WORD_UPDATE,
            self::DICTIONARY_WORD_DELETE,
            self::DICTIONARY_WORD_IMPORT,
        ];
    }
}
