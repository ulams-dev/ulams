<?php

namespace Ulams\Interactive\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $title
 * @property string $storage_key random UUID: the folder on the package disk, never derived from the id
 * @property int $current_version
 * @property ?int $author_id
 * @property-read InteractivePackageVersion|null $current
 */
class InteractivePackage extends Model
{
    protected $table = 'interactive_packages';

    protected $fillable = ['title', 'storage_key', 'current_version', 'author_id'];

    protected $casts = ['current_version' => 'integer'];

    protected static function booted(): void
    {
        static::creating(function (InteractivePackage $package) {
            $package->storage_key ??= (string) Str::uuid();
        });
    }

    public function versions(): HasMany
    {
        return $this->hasMany(InteractivePackageVersion::class, 'interactive_package_id')->orderBy('version');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(InteractiveTopic::class, 'value');
    }

    /** The current version (a query per call; `current_version` can change under a long-lived model). */
    public function getCurrentAttribute(): ?InteractivePackageVersion
    {
        return $this->version($this->current_version);
    }

    public function version(int $version): ?InteractivePackageVersion
    {
        return $this->versions()->where('version', $version)->first();
    }

    /** The folder of a version on the package disk. */
    public function directory(int $version): string
    {
        return sprintf('interactive/%s/v%d', $this->storage_key, $version);
    }
}
