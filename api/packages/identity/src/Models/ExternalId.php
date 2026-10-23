<?php

namespace Ulams\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The SCIM identity of one local user or group at one provider.
 *
 * @property int $id
 * @property string $provider_id
 * @property string $resource_type user | group
 * @property int $local_id
 * @property string|null $external_id
 * @property string|null $user_name
 * @property string|null $user_name_key
 * @property string $source
 * @property array<string,mixed>|null $meta
 * @property \Illuminate\Support\Carbon|null $deleted_at set when the client deprovisioned the resource
 */
class ExternalId extends Model
{
    public const USER = 'user';
    public const GROUP = 'group';

    protected $table = 'identity_external_ids';

    protected $guarded = [];

    protected $casts = ['meta' => 'array', 'deleted_at' => 'datetime'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(IdentityProvider::class, 'provider_id');
    }
}
