<?php

namespace Ulams\CourseBuilder\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Blueprint element → LMS entity (`course`, `lesson`, `topic`, `quiz_topic`, `gift_question`,
 * `page`). The fingerprint is a hash of the element as last applied, so a re-apply only touches
 * what changed.
 *
 * @property int $id
 * @property string $session_id
 * @property string $element_id
 * @property string $entity_type
 * @property int $entity_id
 * @property string|null $fingerprint
 * @property string|null $applied_version_id
 * @property \Illuminate\Support\Carbon|null $retired_at set when the entity was deactivated instead of deleted
 * @property string|null $added_by_proposal_id the update proposal that created the entity
 */
class EntityMapEntry extends Model
{
    protected $table = 'course_builder_entity_map';

    protected $guarded = [];

    protected $casts = ['entity_id' => 'integer', 'retired_at' => 'datetime'];
}
