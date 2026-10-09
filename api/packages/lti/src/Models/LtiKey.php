<?php

namespace Ulams\Lti\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $kid
 * @property string $private_key PEM, encrypted at rest
 * @property array $public_jwk
 * @property string $status
 * @property ?Carbon $activated_at
 * @property ?Carbon $retired_at
 */
class LtiKey extends Model
{
    public const NEXT = 'next';
    public const ACTIVE = 'active';
    public const RETIRED = 'retired';

    protected $table = 'lti_keys';

    protected $fillable = ['kid', 'private_key', 'public_jwk', 'status', 'activated_at', 'retired_at'];

    protected $hidden = ['private_key'];

    protected $casts = [
        'private_key' => 'encrypted',
        'public_jwk' => 'array',
        'activated_at' => 'datetime',
        'retired_at' => 'datetime',
    ];
}
