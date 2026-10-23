<?php

namespace Ulams\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What the platform last published to an academy: a hash of the list of active entry hosts. Platform database only.
 *
 * @property int $tenant_id
 * @property string $hash
 */
class EntryHostPublication extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'tenant_id';

    protected $table = 'tenant_entry_host_publications';

    protected $guarded = [];
}
