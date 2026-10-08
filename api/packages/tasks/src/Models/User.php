<?php

namespace EscolaLms\Tasks\Models;

use EscolaLms\Core\Models\User as CoreUser;

/**
 * Class User
 *
 * @package EscolaLms\Tasks\Models
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
