<?php

namespace Ulams\Compliance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $course_id
 * @property int $competency_id
 * @property string $status suggested or confirmed
 * @property string $source author or ai
 * @property string|null $rationale
 * @property list<int>|null $lesson_ids
 * @property int|null $ai_call_id
 * @property int|null $confirmed_by
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 */
class CourseCompetency extends Model
{
    public const SUGGESTED = 'suggested';
    public const CONFIRMED = 'confirmed';

    protected $table = 'compliance_course_competencies';

    protected $guarded = ['id'];

    protected $casts = ['lesson_ids' => 'array', 'confirmed_at' => 'datetime'];

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }
}
