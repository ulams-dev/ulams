<?php

namespace Ulams\AuthoringMetrics\Models;

use Illuminate\Database\Eloquent\Model;

/** Snapshot of how a course was produced (see the migration for each column). */
class CourseMetric extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'authoring_course_metrics';

    protected $primaryKey = 'course_id';

    protected $guarded = [];

    protected $casts = ['computed_at' => 'datetime', 'course_id' => 'integer'];
}
