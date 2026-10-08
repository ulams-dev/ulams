<?php

namespace EscolaLms\Settings\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class SettingsPermissionsEnum extends BasicEnum
{
    const SETTINGS_MANAGE = 'settings_manage';
    //
    const SETTINGS_CREATE = 'settings_create';
    const SETTINGS_DELETE = 'settings_delete';
    const SETTINGS_UPDATE = 'settings_update';
    const SETTINGS_READ   = 'settings_read';
    const SETTINGS_LIST   = 'settings_list';
    const SETTINGS_LIST_READONLY   = 'settings_list_readonly';
    //
    const CONFIG_LIST     = 'settings_config_list';
    const CONFIG_UPDATE   = 'settings_config_update';
}
