<?php

namespace Ulams\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A one-off upgrade step that finished for a target (`platform` or a tenant slug). Platform database only.
 *
 * @property int $id
 * @property string $target
 * @property string $step
 * @property ?string $since
 * @property \Illuminate\Support\Carbon $ran_at
 */
class TenantUpgradeStep extends Model
{
    public const PLATFORM = 'platform';

    public $timestamps = false;

    protected $table = 'tenant_upgrade_steps';

    protected $fillable = ['target', 'step', 'since', 'ran_at'];

    protected $casts = ['ran_at' => 'datetime'];
}
