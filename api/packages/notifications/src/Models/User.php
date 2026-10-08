<?php

namespace Ulams\Notifications\Models;

use Ulams\Core\Models\User as CoreUser;
use Ulams\Notifications\Models\Traits\HasEventNotifications;

class User extends CoreUser
{
    use HasEventNotifications;
}
