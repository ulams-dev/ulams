<?php

namespace EscolaLms\Settings\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class SettingTypes extends BasicEnum
{
    const TEXT = 'text';
    const MARKDOWN = 'markdown';
    const JSON = 'json';
    const IMAGE = 'image';
    const FILE = 'file';
    const CONFIG = 'config';
    const BOOLEAN = 'boolean';
    const NUMBER = 'number';
    const ARRAY = 'array';
}
