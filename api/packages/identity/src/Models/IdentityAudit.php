<?php

namespace Ulams\Identity\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row of the append-only, hash-chained identity audit trail. Written only by IdentityAuditLog.
 *
 * @property int $id
 * @property string $actor
 * @property int|null $actor_id
 * @property string|null $provider_id
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array|null $data
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $request_id
 * @property string $prev_hash
 * @property string $hash
 */
class IdentityAudit extends Model
{
    public $timestamps = false;

    protected $table = 'identity_audit';

    protected $guarded = [];

    protected $casts = ['data' => 'array', 'created_at' => 'datetime'];
}
