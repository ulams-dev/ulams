<?php

namespace Ulams\Auth\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $protocol
 * @property string $provider
 * @property string $host
 * @property string $return_to
 * @property string $challenge
 * @property array $data
 */
class LoginHandoffState extends Model
{
    protected $table = 'login_handoff_states';

    protected $guarded = [];

    protected $hidden = ['state_hash'];

    protected $casts = [
        'data' => 'encrypted:array',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];
}
