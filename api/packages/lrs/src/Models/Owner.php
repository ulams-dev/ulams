<?php

namespace Ulams\Lrs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * The owner of a learning record store: every client, statement and document belongs to one.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property array $meta
 */
class Owner extends Model
{
    use SoftDeletes;

    protected $table = 'trax_owners';

    protected $casts = [
        'meta' => 'array',
    ];

    protected $attributes = [
        'meta' => '[]',
    ];

    protected $fillable = ['uuid', 'name', 'meta'];

    protected static function booted(): void
    {
        static::creating(function (Owner $owner) {
            $owner->uuid ??= (string) Str::uuid();
        });
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
