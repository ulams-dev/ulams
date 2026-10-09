<?php

namespace Ulams\CourseBuilder\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One AG-UI event; its id is the SSE event id (resume with Last-Event-ID).
 *
 * @property int $id
 * @property string $session_id
 * @property string|null $run_id
 * @property string $type
 * @property array $payload
 */
class Event extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'course_builder_events';

    protected $guarded = [];

    protected $casts = ['payload' => 'array'];
}
