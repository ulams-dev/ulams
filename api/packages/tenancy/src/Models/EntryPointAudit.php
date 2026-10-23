<?php

namespace Ulams\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One change of an entry point: who, what, when. Append only, platform database only.
 *
 * @property int $id
 * @property int $tenant_id
 * @property ?string $entry_point_id
 * @property string $action created|updated|enabled|disabled|deleted
 * @property string $host
 * @property ?int $actor_id
 * @property ?string $actor_email
 * @property ?array $changes
 * @property \Illuminate\Support\Carbon $created_at
 */
class EntryPointAudit extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'tenant_entry_point_audits';

    protected $guarded = [];

    protected $casts = ['changes' => 'array', 'created_at' => 'datetime'];
}
