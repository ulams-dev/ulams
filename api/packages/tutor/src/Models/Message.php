<?php

namespace Ulams\Tutor\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $conversation_id
 * @property string $role learner | tutor
 * @property string $text
 * @property array|null $citations
 * @property string|null $refusal none | out_of_scope | attempt_active | unsafe
 * @property array|null $flags
 */
class Message extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'tutor_messages';

    protected $guarded = [];

    protected $casts = ['citations' => 'array', 'flags' => 'array', 'created_at' => 'datetime'];
}
