<?php

namespace Ulams\Webhooks\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One HTTP attempt of a delivery: what the receiver answered (cut to a few KB) or why there was no answer.
 *
 * @property int $id
 * @property string $delivery_id
 * @property int $number
 * @property int|null $status_code
 * @property string|null $response_body
 * @property string|null $error
 * @property int $duration_ms
 */
class WebhookAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'webhook_attempts';

    protected $guarded = [];

    protected $casts = ['number' => 'integer', 'status_code' => 'integer', 'duration_ms' => 'integer', 'created_at' => 'datetime'];
}
