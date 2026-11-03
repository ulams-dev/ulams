<?php

namespace Ulams\Auth\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $kind
 * @property string $host
 * @property int|null $user_id
 * @property string $challenge
 * @property bool $remember_me
 * @property string|null $payload
 */
class LoginHandoffCode extends Model
{
    public const KIND_LOGIN = 'login';
    public const KIND_COMPLETE = 'complete';
    /** A session handed from one host of the academy to another (ADR 0195). */
    public const KIND_HANDOFF = 'handoff';

    protected $table = 'login_handoff_codes';

    protected $guarded = [];

    protected $hidden = ['code_hash', 'payload'];

    protected $casts = [
        'remember_me' => 'boolean',
        'payload' => 'encrypted',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];
}
