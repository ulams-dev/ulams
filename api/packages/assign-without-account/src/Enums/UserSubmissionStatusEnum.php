<?php

namespace EscolaLms\AssignWithoutAccount\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class UserSubmissionStatusEnum extends BasicEnum
{
    const REJECTED = 'rejected';
    const ACCEPTED = 'accepted';
    const ASSIGNED = 'assigned';
    const SENT = 'sent';
}
