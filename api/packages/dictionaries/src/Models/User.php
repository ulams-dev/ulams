<?php

namespace Ulams\Dictionaries\Models;

use Ulams\Auth\Models\User as AuthUser;

/**
 * Class Ulams\Dictionaries\Models\User
 *
 * @property int $id
 * @property string $email
 * @property-read string $name
 */
class User extends AuthUser
{
}
