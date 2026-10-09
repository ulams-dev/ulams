<?php

namespace Ulams\Lti\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit row for every launch, in either direction.
 */
class LtiLaunch extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'lti_launches';

    protected $fillable = [
        'direction', 'message_type', 'lti_tool_id', 'lti_platform_id', 'user_id', 'topic_id',
        'course_id', 'status', 'error',
    ];

    public static function record(array $attributes): void
    {
        static::query()->create($attributes + ['status' => 'ok']);
    }
}
