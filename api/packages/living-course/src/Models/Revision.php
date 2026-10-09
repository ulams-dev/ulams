<?php

namespace Ulams\LivingCourse\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ulams\CourseBuilder\Models\Source;

/**
 * One fetched state of one source, with its own copy of every fragment.
 *
 * @property string $id
 * @property string $source_id
 * @property string $connection_id
 * @property int $number
 * @property string $origin initial | upload | git | url | plugin
 * @property string|null $origin_ref
 * @property string $trigger initial | manual | upload | poll | webhook
 * @property int|null $triggered_by
 * @property string $status fetched | ingested | unchanged | no_impact | failed
 * @property string|null $raw_path
 * @property string|null $markdown_path
 * @property string|null $normalised_sha256
 * @property array|null $metadata
 * @property int $fragment_count
 * @property int $token_estimate
 * @property string|null $error
 */
class Revision extends Model
{
    use HasUlids;

    public const STATUSES = ['fetched', 'ingested', 'unchanged', 'no_impact', 'failed'];

    protected $table = 'living_course_revisions';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'number' => 'integer',
        'fragment_count' => 'integer',
        'token_estimate' => 'integer',
        'detected_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class, 'connection_id');
    }

    public function fragments(): HasMany
    {
        return $this->hasMany(RevisionFragment::class, 'revision_id')->orderBy('ordinal');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(FragmentChange::class, 'to_revision_id')->orderBy('id');
    }
}
