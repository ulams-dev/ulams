<?php

namespace Ulams\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An active entry host in an academy's own database (a copy published by the platform, never written by a request).
 *
 * @property string $host
 * @property string $origin
 * @property string $kind
 * @property string $key
 */
class PublishedEntryHost extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $primaryKey = 'host';

    protected $table = 'tenant_entry_hosts';

    protected $guarded = [];
}
