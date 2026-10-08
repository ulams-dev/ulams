<?php

namespace Ulams\AssignWithoutAccount\Enums;

use Ulams\Core\Enums\BasicEnum;

class UserSubmissionStatusEnum extends BasicEnum
{
    const REJECTED = 'rejected';
    const ACCEPTED = 'accepted';
    const ASSIGNED = 'assigned';
    const SENT = 'sent';
}
