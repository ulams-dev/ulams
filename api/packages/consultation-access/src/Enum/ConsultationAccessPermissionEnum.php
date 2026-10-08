<?php

namespace EscolaLms\ConsultationAccess\Enum;

use EscolaLms\Core\Enums\BasicEnum;

class ConsultationAccessPermissionEnum extends BasicEnum
{
    const CREATE_OWN_CONSULTATION_ACCESS_ENQUIRY = 'consultation-access_create-own';
    const LIST_OWN_CONSULTATION_ACCESS_ENQUIRY = 'consultation-access_list-own';
    const READ_OWN_CONSULTATION_ACCESS_ENQUIRY = 'consultation-access_read-own';
    const DELETE_OWN_CONSULTATION_ACCESS_ENQUIRY = 'consultation-access_delete-own';
    const UPDATE_OWN_CONSULTATION_ACCESS_ENQUIRY = 'consultation-access_update-own';

    const LIST_CONSULTATION_ACCESS_ENQUIRY = 'consultation-access_list';
    const APPROVE_CONSULTATION_ACCESS_ENQUIRY = 'consultation-access_approve';
    const DISAPPROVE_CONSULTATION_ACCESS_ENQUIRY = 'consultation-access_disapprove';
}
