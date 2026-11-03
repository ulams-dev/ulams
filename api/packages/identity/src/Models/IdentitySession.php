<?php

namespace Ulams\Identity\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The IdP session behind a Passport token: what single logout needs to find the tokens of a LogoutRequest
 * (NameID and SessionIndex) and to build ours.
 *
 * @property int $id
 * @property string $provider_id
 * @property int $user_id
 * @property string $token_id
 * @property string $name_id
 * @property string|null $name_id_format
 * @property string|null $session_index
 */
class IdentitySession extends Model
{
    public $timestamps = false;

    protected $table = 'identity_sessions';

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime', 'ended_at' => 'datetime'];
}
