<?php

namespace Ulams\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A course or a bundle sold for a number of days.
 *
 * @property int $id
 * @property string $name
 * @property string $subject_type course or bundle
 * @property int $subject_id
 * @property int $days
 */
class AccessPass extends Model
{
    protected $table = 'commerce_access_passes';

    protected $guarded = ['id'];

    protected $casts = ['subject_id' => 'integer', 'days' => 'integer'];
}
