<?php

namespace Ulams\Auth\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only: rows are inserted by `RecordAgentAudit` and deleted only by retention pruning.
 *
 * @property int $id
 * @property string|null $token_id
 * @property int|null $user_id
 * @property string|null $agent_name
 * @property string|null $client
 * @property string $method
 * @property string $path
 * @property int $status
 */
class AgentAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'agent_audit_log';

    protected $guarded = [];

    protected $casts = ['route_params' => 'array', 'dry_run' => 'boolean', 'status' => 'integer', 'duration_ms' => 'integer'];

    protected static function booted(): void
    {
        static::updating(fn () => false);
    }
}
