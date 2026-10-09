<?php

namespace Ulams\Auth\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $device_code_hash
 * @property string $user_code_hash
 * @property string $client_name
 * @property string|null $agent_name
 * @property list<string> $requested_scopes
 * @property list<string>|null $approved_scopes
 * @property string $status pending | approved | denied | consumed | expired
 * @property int|null $user_id
 * @property string|null $token_id
 * @property string|null $access_token_encrypted
 * @property string|null $ip
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $last_polled_at
 * @property \Illuminate\Support\Carbon $expires_at
 */
class DeviceAuthorization extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DENIED = 'denied';

    public const CONSUMED = 'consumed';

    public const EXPIRED = 'expired';

    protected $table = 'device_authorizations';

    protected $guarded = [];

    protected $hidden = ['device_code_hash', 'user_code_hash', 'access_token_encrypted'];

    protected $casts = [
        'requested_scopes' => 'array',
        'approved_scopes' => 'array',
        'last_polled_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING && !$this->isExpired();
    }
}
