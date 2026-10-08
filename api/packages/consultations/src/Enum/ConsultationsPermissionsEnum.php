<?php

namespace EscolaLms\Consultations\Enum;

use EscolaLms\Core\Enums\BasicEnum;

class ConsultationsPermissionsEnum extends BasicEnum
{
    const CONSULTATION_LIST = 'consultation_list';
    const CONSULTATION_LIST_OWN = 'consultation_list-own';
    const CONSULTATION_CREATE = 'consultation_create';
    const CONSULTATION_UPDATE = 'consultation_update';
    const CONSULTATION_UPDATE_OWN = 'consultation_update-own';
    const CONSULTATION_DELETE = 'consultation_delete';
    const CONSULTATION_DELETE_OWN = 'consultation_delete-own';
    const CONSULTATION_READ = 'consultation_read';
    const CONSULTATION_READ_OWN = 'consultation_read-own';
    const CONSULTATION_CHANGE_TERM = 'consultation_change_term';
}
