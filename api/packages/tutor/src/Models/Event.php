<?php

namespace Ulams\Tutor\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One AG-UI event of a streamed tutor answer (ADR 0105). Transport only: the stored `Message` is the record.
 *
 * @property int $id
 * @property string $run_id
 * @property string $conversation_id
 * @property string $type
 * @property array<string,mixed> $payload
 */
class Event extends Model
{
    public $timestamps = false;

    protected $table = 'tutor_events';

    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'created_at' => 'datetime'];
}
