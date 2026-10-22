<?php

namespace Ulams\CourseBuilder\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A variant of a builder course for one audience (a level, a role or both): a branch of the session that shares
 * its sources and citations (ADR 0192). The session row is the base branch; a variant has its own versions, its own
 * entity map and, after the first apply, its own LMS course.
 *
 * @property string $id
 * @property string $session_id
 * @property string $key slug, unique per session
 * @property string $label shown to learners, e.g. "Expert, developer"
 * @property string|null $level beginner | intermediate | expert
 * @property string|null $role
 * @property array $audience_brief what the interview decided for this audience
 * @property array|null $plan lesson id => {action: keep|condense|deepen|skip, note}, plus the added lessons
 * @property int|null $course_id
 * @property string|null $current_version_id
 * @property string|null $applied_version_id
 * @property string|null $based_on_version_id the base version this branch was derived from
 * @property string $status draft | approved | retired
 * @property int $position
 */
class Variant extends Model
{
    use HasUlids;

    public const DRAFT = 'draft';
    public const APPROVED = 'approved';
    public const RETIRED = 'retired';

    public const LEVELS = ['beginner', 'intermediate', 'expert'];

    public const SUBJECT_TYPE = 'course_builder_variant';

    protected $table = 'course_builder_variants';

    protected $guarded = [];

    protected $casts = [
        'audience_brief' => 'array',
        'plan' => 'array',
        'course_id' => 'integer',
        'position' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(Version::class, 'current_version_id');
    }

    /** @return array{type:string,id:string} what its LLM calls are logged against */
    public function subject(): array
    {
        return ['type' => self::SUBJECT_TYPE, 'id' => $this->id];
    }
}
