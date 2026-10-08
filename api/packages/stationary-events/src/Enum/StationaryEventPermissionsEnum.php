<?php

namespace EscolaLms\StationaryEvents\Enum;

use EscolaLms\Core\Enums\BasicEnum;

class StationaryEventPermissionsEnum extends BasicEnum
{
    public const STATIONARY_EVENT_LIST = 'stationary-event_list';
    public const STATIONARY_EVENT_CREATE = 'stationary-event_create';
    public const STATIONARY_EVENT_READ = 'stationary-event_read';
    public const STATIONARY_EVENT_UPDATE = 'stationary-event_update';
    public const STATIONARY_EVENT_DELETE = 'stationary-event_delete';
}
