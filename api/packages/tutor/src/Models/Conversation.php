<?php

namespace Ulams\Tutor\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A learner's conversation with the tutor of one course. Owned by the learner; deleted by them or after the retention period.
 *
 * @property string $id
 * @property int $user_id
 * @property int $course_id
 */
class Conversation extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'tutor_conversations';

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime', 'last_message_at' => 'datetime', 'expires_at' => 'datetime'];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id')->orderBy('created_at')->orderBy('id');
    }
}
