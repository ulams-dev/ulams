<?php

namespace Ulams\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Courses sold together as one product. Buying it grants one entitlement per course, keyed by the order line and the
 * course, so a refund revokes them all and courses added later are not granted to past buyers (ADR 0130, Q6).
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 */
class Bundle extends Model
{
    protected $table = 'commerce_bundles';

    protected $guarded = ['id'];

    /** @return list<int> */
    public function courseIds(): array
    {
        return BundleCourse::query()->where('bundle_id', $this->getKey())->orderBy('course_id')->pluck('course_id')->map(fn ($id) => (int) $id)->all();
    }
}
