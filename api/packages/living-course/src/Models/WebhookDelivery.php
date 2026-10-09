<?php

namespace Ulams\LivingCourse\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One received webhook delivery (diagnostics and duplicate detection; the payload itself is not kept).
 *
 * @property int $id
 * @property string $connection_id
 * @property string $delivery_id
 * @property string|null $event
 * @property bool $signature_valid
 * @property string $outcome queued | ignored | duplicate | rejected | dropped
 * @property string $payload_sha256
 */
class WebhookDelivery extends Model
{
    public $timestamps = false;

    protected $table = 'living_course_webhook_deliveries';

    protected $guarded = [];

    protected $casts = ['signature_valid' => 'boolean', 'received_at' => 'datetime'];
}
