<?php

namespace Ulams\Lrs\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Credentials record an access points to. Requests are authenticated with the learner's
 * Passport token (see AccessTokenGuard), so the stored password is never checked.
 *
 * @property int $id
 * @property string $username
 */
class BasicHttpCredentials extends Model
{
    protected $table = 'trax_basic_http';

    public $timestamps = false;

    protected $fillable = ['username', 'password'];

    protected $hidden = ['password'];
}
