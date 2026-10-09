<?php

namespace Ulams\Adapt\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property int $current_version
 * @property string $status draft | building | built | failed
 * @property ?int $built_version
 * @property ?int $scorm_id package built from the source (played as a SCORM topic)
 * @property ?string $last_error
 * @property ?int $author_id
 */
class AdaptSource extends Model
{
    public const DRAFT = 'draft';
    public const BUILDING = 'building';
    public const BUILT = 'built';
    public const FAILED = 'failed';

    protected $table = 'adapt_sources';

    protected $fillable = ['title', 'current_version', 'status', 'built_version', 'scorm_id', 'last_error', 'author_id'];

    public function versions(): HasMany
    {
        return $this->hasMany(AdaptSourceVersion::class, 'adapt_source_id')->orderBy('version');
    }

    public function version(int $version): ?AdaptSourceVersion
    {
        return $this->versions()->where('version', $version)->first();
    }
}
