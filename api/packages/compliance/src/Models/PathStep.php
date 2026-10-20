<?php

namespace Ulams\Compliance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ulams\Courses\Models\Course;

/**
 * @property int $path_id
 * @property int $course_id
 * @property int $position
 * @property list<int>|null $prerequisites course ids of the same path
 */
class PathStep extends Model
{
    public $timestamps = false;

    protected $table = 'compliance_path_steps';

    protected $guarded = ['id'];

    protected $casts = ['path_id' => 'integer', 'course_id' => 'integer', 'position' => 'integer', 'prerequisites' => 'array'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return list<int> */
    public function prerequisiteIds(): array
    {
        return array_values(array_map('intval', $this->prerequisites ?? []));
    }
}
