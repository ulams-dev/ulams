<?php

namespace Ulams\LivingCourse\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A note for one learner about an update of a course they took part in.
 *
 * @property int $id
 * @property int $user_id
 * @property int $course_id
 * @property int $topic_id
 * @property int $gift_question_id 0 when the notice is about the topic as a whole
 * @property string $kind topic_updated | question_reattempt | topic_retired | course_extended
 * @property string $proposal_id
 * @property string|null $message
 * @property string $status open | done | dismissed
 */
class LearnerNotice extends Model
{
    public const KINDS = ['topic_updated', 'question_reattempt', 'topic_retired', 'course_extended'];

    public $timestamps = false;

    protected $table = 'living_course_learner_notices';

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime', 'resolved_at' => 'datetime'];
}
