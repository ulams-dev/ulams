<?php

namespace Ulams\Lrs\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A cmi5 launch: the one-time token handed to the AU in the launch URL, and the LRS session that
 * the first fetch opens (ADR 0046).
 *
 * @property int $id
 * @property string $token_hash
 * @property int $user_id
 * @property string $registration
 * @property int|null $au_id
 * @property string $access_uuid
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $used_at
 */
class LaunchToken extends Model
{
    protected $table = 'lrs_launch_tokens';

    protected $guarded = ['id'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
