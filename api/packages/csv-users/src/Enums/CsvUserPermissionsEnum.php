<?php

namespace EscolaLms\CsvUsers\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class CsvUserPermissionsEnum extends BasicEnum
{
    const CSV_USERS_EXPORT = 'csv-users_export';
    const CSV_USERS_IMPORT = 'csv-users_import';
    const CSV_USER_GROUP_EXPORT = 'csv-user_group_export';
    const CSV_USER_GROUP_IMPORT = 'csv-user_group_import';
}
