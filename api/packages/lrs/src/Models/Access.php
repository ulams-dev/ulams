<?php

namespace Ulams\Lrs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An access to the xAPI endpoints of a store. Its UUID is part of the endpoint URL:
 * `{app.url}/trax/api/{uuid}/xapi/std`.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property bool $active
 * @property int $client_id
 * @property string $credentials_type
 * @property-read Client $client
 * @property-read string $xapi_endpoint
 */
class Access extends Model
{
    public const TYPE_BASIC_HTTP = 'basic_http';

    protected $table = 'trax_accesses';

    protected $casts = [
        'active' => 'boolean',
        'admin' => 'boolean',
        'visible' => 'boolean',
        'inherited_permissions' => 'boolean',
        'meta' => 'array',
        'permissions' => 'array',
    ];

    protected $attributes = [
        'cors' => '',
        'meta' => '[]',
        'permissions' => '[]',
        'active' => true,
        'admin' => false,
        'inherited_permissions' => true,
        'visible' => true,
    ];

    protected $fillable = ['uuid', 'name', 'cors', 'active', 'meta', 'client_id', 'credentials_id', 'credentials_type', 'category'];

    protected static function booted(): void
    {
        static::creating(function (Access $access) {
            $access->uuid ??= (string) Str::uuid();
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Credentials type in snake case, e.g. `basic_http`. Older rows store a class name.
     */
    public function type(): string
    {
        return (string) Str::of(class_basename($this->credentials_type))->snake();
    }

    public function isActive(): bool
    {
        return $this->active && $this->client && $this->client->active;
    }

    public function ownerId(): ?int
    {
        return $this->client?->owner_id;
    }

    public function getXapiEndpointAttribute(): string
    {
        return rtrim((string) config('app.url'), '/') . '/trax/api/' . $this->uuid . '/xapi/std';
    }
}
