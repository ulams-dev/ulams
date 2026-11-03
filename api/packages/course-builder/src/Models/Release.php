<?php

namespace Ulams\CourseBuilder\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One release of a builder course (ADR 0192): a version that was applied to the LMS course.
 *
 * @property string $id
 * @property string $session_id
 * @property string|null $variant_id null is the base branch (variants arrive with CV-4)
 * @property int $number sequential per (session, variant)
 * @property string $version_id
 * @property string|null $previous_release_id
 * @property int $course_id
 * @property string $trigger generate | author | chat | update | insights | import | restore | rebase
 * @property array|null $source_revisions
 * @property int|null $approved_by
 * @property array $summary
 * @property \Illuminate\Support\Carbon $released_at
 */
class Release extends Model
{
    use HasUlids;

    /** @var string[] */
    public const TRIGGERS = ['generate', 'author', 'chat', 'update', 'insights', 'import', 'restore', 'rebase'];

    public $timestamps = false;

    protected $table = 'course_builder_releases';

    protected $guarded = [];

    protected $casts = [
        'number' => 'integer',
        'course_id' => 'integer',
        'approved_by' => 'integer',
        'source_revisions' => 'array',
        'summary' => 'array',
        'released_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class, 'version_id');
    }
}
