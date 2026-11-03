<?php

namespace Ulams\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $bundle_id
 * @property int $course_id
 */
class BundleCourse extends Model
{
    protected $table = 'commerce_bundle_courses';

    public $timestamps = false;

    protected $guarded = ['id'];
}
