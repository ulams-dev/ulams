<?php

namespace EscolaLms\Templates\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class TemplatesPermissionsEnum extends BasicEnum
{
    const TEMPLATES_CREATE = 'template_create';
    const TEMPLATES_DELETE = 'template_delete';
    const TEMPLATES_UPDATE = 'template_update';
    const TEMPLATES_LIST = 'template_list';
    const TEMPLATES_READ = 'template_read';
    const EVENTS_TRIGGER = 'events_trigger';
}
