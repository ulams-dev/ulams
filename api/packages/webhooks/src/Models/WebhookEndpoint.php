<?php

namespace Ulams\Webhooks\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A URL of the tenant that receives signed event payloads.
 *
 * @property string $id
 * @property string $url
 * @property string|null $description
 * @property list<string> $events event types, or ["*"] for all
 * @property string $api_version
 * @property string $secret the signing secret (encrypted at rest)
 * @property string $secret_hint last characters of the secret, to tell secrets apart
 * @property bool $enabled
 * @property string|null $disabled_reason
 * @property int $consecutive_failures
 * @property int|null $created_by
 */
class WebhookEndpoint extends Model
{
    use HasUlids;

    protected $table = 'webhook_endpoints';

    protected $guarded = [];

    protected $hidden = ['secret'];

    protected $casts = [
        'events' => 'array',
        'enabled' => 'boolean',
        'secret' => 'encrypted',
        'consecutive_failures' => 'integer',
        'last_delivery_at' => 'datetime',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'endpoint_id');
    }

    public function subscribesTo(string $type): bool
    {
        return in_array('*', $this->events, true) || in_array($type, $this->events, true);
    }
}
