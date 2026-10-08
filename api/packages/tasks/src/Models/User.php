<?php

namespace Ulams\Tasks\Models;

use Ulams\Core\Models\User as CoreUser;

/**
 * Class User
 *
 * @package Ulams\Tasks\Models
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 *
 */
class User extends CoreUser
{

}
