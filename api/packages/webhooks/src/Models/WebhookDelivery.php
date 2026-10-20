<?php

namespace Ulams\Webhooks\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One event for one endpoint. `payload` is the exact JSON body that is signed and sent.
 *
 * @property string $id
 * @property string $endpoint_id
 * @property string $event_id
 * @property string $event_type
 * @property string $payload
 * @property string $status pending | retrying | succeeded | failed | cancelled
 * @property int $attempts
 * @property int|null $last_status_code
 * @property bool $is_test
 * @property string|null $replay_of
 */
class WebhookDelivery extends Model
{
    use HasUlids;

    public const PENDING = 'pending';

    public const RETRYING = 'retrying';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    protected $table = 'webhook_deliveries';

    protected $guarded = [];

    protected $casts = [
        'attempts' => 'integer',
        'last_status_code' => 'integer',
        'is_test' => 'boolean',
        'next_attempt_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id');
    }

    public function attemptLog(): HasMany
    {
        return $this->hasMany(WebhookAttempt::class, 'delivery_id')->orderBy('number');
    }
}
