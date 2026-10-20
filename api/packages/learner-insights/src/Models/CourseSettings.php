<?php

namespace Ulams\LearnerInsights\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-course overrides of the rule thresholds.
 *
 * @property int $course_id
 * @property array|null $rules
 * @property array|null $switches
 * @property int|null $updated_by
 */
class CourseSettings extends Model
{
    protected $table = 'learner_insights_course_settings';

    protected $primaryKey = 'course_id';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = ['rules' => 'array', 'switches' => 'array'];
}
