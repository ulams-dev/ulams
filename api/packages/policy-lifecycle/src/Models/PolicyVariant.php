<?php

namespace Ulams\PolicyLifecycle\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The policy as taught to one department: a Course Builder session holding its own copy of the policy source, the LMS
 * course the session applied, and the group that has to take it.
 *
 * @property string $id
 * @property string $policy_id
 * @property string $department_label
 * @property int|null $group_id
 * @property array $brief duration, seniority and prior experience of the department
 * @property string|null $session_id
 * @property string|null $source_id
 * @property int|null $course_id
 * @property int|null $path_id
 * @property int|null $rule_id
 * @property int|null $exam_course_id the exam-only course (the department course's final test), made by the first minor change
 * @property string|null $exam_session_id
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $generation_requested_at
 */
class PolicyVariant extends Model
{
    use HasUlids;

    public const SENIORITY = ['junior', 'mid', 'senior', 'mixed'];
    public const EXPERIENCE = ['none', 'some', 'expert'];

    protected $table = 'policy_variants';

    protected $guarded = [];

    protected $casts = ['brief' => 'array', 'group_id' => 'integer', 'course_id' => 'integer', 'path_id' => 'integer', 'rule_id' => 'integer', 'exam_course_id' => 'integer', 'position' => 'integer', 'generation_requested_at' => 'datetime'];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }
}
