<?php

namespace EscolaLms\Dictionaries\Models;

use EscolaLms\Auth\Models\User as AuthUser;

/**
 * Class EscolaLms\Dictionaries\Models\User
 *
 * @property int $id
 * @property string $email
 * @property-read string $name
 */
class User extends AuthUser
{
}
