<?php

namespace EscolaLms\AssignWithoutAccount\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class AssignWithoutAccountPermissionEnum extends BasicEnum
{
    const USER_SUBMISSION_LIST = 'assign-without-account_user-submission_list';
    const USER_SUBMISSION_CREATE = 'assign-without-account_user-submission_create';
    const USER_SUBMISSION_UPDATE = 'assign-without-account_user-submission_update';
    const USER_SUBMISSION_DELETE = 'assign-without-account_user-submission_delete';
}
