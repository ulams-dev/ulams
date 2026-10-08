<?php

namespace EscolaLms\Notifications\Models;

use EscolaLms\Core\Models\User as CoreUser;
use EscolaLms\Notifications\Models\Traits\HasEventNotifications;

class User extends CoreUser
{
    use HasEventNotifications;
}
