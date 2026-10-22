<?php

namespace Ulams\PolicyLifecycle\Enums;

use Ulams\Core\Enums\BasicEnum;

class PolicyPermissionsEnum extends BasicEnum
{
    /** Every policy of the tenant: create, review, approve, act for an absent owner. */
    const MANAGE = 'policy_manage';
    /** The policies the user owns. */
    const OWNER = 'policy_owner';
}
