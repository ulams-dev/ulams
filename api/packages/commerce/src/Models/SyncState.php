<?php

namespace Ulams\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $provider
 * @property string $channel
 * @property string|null $cursor
 */
class SyncState extends Model
{
    protected $table = 'commerce_sync_state';

    protected $guarded = ['id'];

    protected $casts = ['last_run_at' => 'datetime', 'last_applied' => 'integer'];
}
