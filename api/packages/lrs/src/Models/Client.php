<?php

namespace Ulams\Lrs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An application allowed to use the xAPI endpoints of a store, through one or more accesses.
 *
 * @property int $id
 * @property string $name
 * @property bool $active
 * @property array $meta
 * @property array $permissions
 * @property int|null $owner_id
 * @property int|null $entity_id
 */
class Client extends Model
{
    protected $table = 'trax_clients';

    protected $casts = [
        'active' => 'boolean',
        'admin' => 'boolean',
        'visible' => 'boolean',
        'meta' => 'array',
        'permissions' => 'array',
    ];

    protected $attributes = [
        'meta' => '[]',
        'permissions' => '[]',
        'active' => true,
        'admin' => false,
        'visible' => true,
    ];

    protected $fillable = ['name', 'active', 'meta', 'permissions', 'owner_id', 'entity_id', 'category'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(Access::class);
    }
}
