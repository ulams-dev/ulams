<?php

namespace Ulams\CourseBuilder\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Blueprint element → LMS entity (`course`, `lesson`, `topic`, `quiz_topic`, `gift_question`,
 * `page`). The fingerprint is a hash of the element as last applied, so a re-apply only touches
 * what changed.
 *
 * @property int $id
 * @property string $session_id
 * @property string $element_id
 * @property string $entity_type
 * @property int $entity_id
 * @property string|null $fingerprint
 * @property string|null $applied_version_id
 * @property \Illuminate\Support\Carbon|null $retired_at set when the entity was deactivated instead of deleted
 * @property string|null $added_by_proposal_id the update proposal that created the entity
 * @property string $variant_id '' for the base branch, else the id of the variant the entity belongs to (ADR 0192)
 */
class EntityMapEntry extends Model
{
    protected $table = 'course_builder_entity_map';

    protected $guarded = [];

    protected $casts = ['entity_id' => 'integer', 'retired_at' => 'datetime'];

    /**
     * Every existing reader (Living Course, Tutor, Learner Insights, course-as-code) means the base branch, so a
     * global scope keeps them on it; the branch-aware code reads a branch with {@see self::branch()}.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('base', fn (Builder $query) => $query->where($query->getModel()->getTable() . '.variant_id', ''));
    }

    /** The entries of one branch of a session: the base (no variant) or a variant. */
    public static function branch(string $sessionId, ?string $variantId = null): Builder
    {
        return static::withoutGlobalScope('base')->where('session_id', $sessionId)->where('variant_id', $variantId ?? '');
    }
}
