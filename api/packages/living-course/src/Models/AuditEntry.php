<?php

namespace Ulams\LivingCourse\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row of the append-only audit trail (ADR 0034). Written only by `AuditLog::record()`.
 *
 * @property int $id
 * @property string|null $session_id
 * @property int|null $course_id
 * @property string $actor_type user | system | agent
 * @property int|null $actor_id
 * @property int|null $on_behalf_of
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $source_id
 * @property string|null $revision_id
 * @property string|null $origin_ref
 * @property int|null $version_from
 * @property int|null $version_to
 * @property array|null $ai_call_ids
 * @property array|null $data
 * @property string $prev_hash
 * @property string $hash
 */
class AuditEntry extends Model
{
    public $timestamps = false;

    protected $table = 'living_course_audit';

    protected $guarded = [];

    protected $casts = [
        'ai_call_ids' => 'array',
        'data' => 'array',
        'course_id' => 'integer',
        'actor_id' => 'integer',
        'on_behalf_of' => 'integer',
        'version_from' => 'integer',
        'version_to' => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s.u',
    ];
}
