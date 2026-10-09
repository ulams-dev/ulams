<?php

namespace Ulams\CourseBuilder\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Course Blueprint version (ADR 0010).
 *
 * @property string $id
 * @property string $session_id
 * @property int $number
 * @property string|null $parent_id
 * @property string $kind outline | content | patch | author | restore | update
 * @property array $document
 * @property array|null $diff_from_parent
 * @property string $origin ai | author | restore
 * @property string|null $reason
 * @property string $status proposed | approved | rejected | superseded
 * @property string|null $element_id
 * @property array|null $ai_call_ids
 * @property array|null $source_revisions {sourceId: revisionId} this version was written from
 */
class Version extends Model
{
    use HasUlids;

    public const PROPOSED = 'proposed';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const SUPERSEDED = 'superseded';

    protected $table = 'course_builder_versions';

    protected $guarded = [];

    protected $casts = [
        'document' => 'array',
        'diff_from_parent' => 'array',
        'ai_call_ids' => 'array',
        'source_revisions' => 'array',
        'number' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
