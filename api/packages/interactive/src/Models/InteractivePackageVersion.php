<?php

namespace Ulams\Interactive\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable version of an interactive package.
 *
 * @property int $id
 * @property int $interactive_package_id
 * @property int $version
 * @property array $manifest
 * @property string $entry
 * @property array $files path => {size, sha256}
 * @property int $total_bytes
 * @property string $licence
 * @property ?string $change_note
 * @property ?int $author_id
 * @property-read InteractivePackage $package
 */
class InteractivePackageVersion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'interactive_package_versions';

    protected $fillable = ['interactive_package_id', 'version', 'manifest', 'entry', 'files', 'total_bytes', 'licence', 'change_note', 'author_id'];

    protected $casts = [
        'manifest' => 'array',
        'files' => 'array',
        'version' => 'integer',
        'total_bytes' => 'integer',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(InteractivePackage::class, 'interactive_package_id');
    }

    /** @return string[] */
    public function stepIds(): array
    {
        return array_values(array_map(fn (array $s) => (string) $s['id'], $this->manifest['steps'] ?? []));
    }

    /** Path of the entry file relative to the content origin: interactive/<key>/v<n>/<entry>. */
    public function entryPath(): string
    {
        return $this->package->directory($this->version) . '/' . $this->entry;
    }

    public function summary(): array
    {
        return [
            'version' => $this->version,
            'manifest_version' => $this->manifest['version'] ?? null,
            'licence' => $this->licence,
            'change_note' => $this->change_note,
            'author_id' => $this->author_id,
            'files' => count($this->files ?? []),
            'total_bytes' => $this->total_bytes,
            'steps' => count($this->manifest['steps'] ?? []),
            'network' => $this->manifest['network'] ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
