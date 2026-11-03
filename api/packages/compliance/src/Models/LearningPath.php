<?php

namespace Ulams\Compliance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An ordered set of courses with prerequisites.
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 */
class LearningPath extends Model
{
    protected $table = 'compliance_paths';

    protected $guarded = ['id'];

    public function steps(): HasMany
    {
        return $this->hasMany(PathStep::class, 'path_id')->orderBy('position')->orderBy('id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(PathEnrollment::class, 'path_id');
    }
}
