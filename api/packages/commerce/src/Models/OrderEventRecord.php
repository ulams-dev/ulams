<?php

namespace Ulams\Commerce\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property string $order_id
 * @property int $sequence
 * @property string $type
 * @property string $source
 * @property string|null $outcome
 * @property Carbon|null $applied_at
 * @property int $attempts
 */
class OrderEventRecord extends Model
{
    public const OUTCOME_APPLIED = 'applied';
    public const OUTCOME_STALE = 'stale';
    public const OUTCOME_REJECTED = 'rejected';
    public const OUTCOME_FAILED = 'failed';

    public $timestamps = false;

    protected $table = 'commerce_order_events';

    protected $guarded = ['id'];

    protected $casts = ['sequence' => 'integer', 'received_at' => 'datetime', 'applied_at' => 'datetime', 'attempts' => 'integer'];
}
