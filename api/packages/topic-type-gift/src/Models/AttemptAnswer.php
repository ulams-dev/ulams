<?php

namespace EscolaLms\TopicTypeGift\Models;

use EscolaLms\TopicTypeGift\Database\Factories\AttemptAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * EscolaLms\TopicTypeGift\Models\AttemptAnswer
 *
 * @property int $id
 * @property int $topic_gift_quiz_attempt_id
 * @property int $topic_gift_question_id
 * @property array $answer
 * @property string $feedback
 * @property double $score
 * @property Carbon|null $graded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read GiftQuestion $question
 * @property-read QuizAttempt $attempt
 */
class AttemptAnswer extends Model
{
    use HasFactory;

    public $table = 'topic_gift_attempt_answers';

    public $fillable = [
        'topic_gift_quiz_attempt_id',
        'topic_gift_question_id',
        'answer',
        'feedback',
        'score',
        'graded_at',
    ];

    public $casts = [
        'score' => 'float',
        'answer' => 'array',
        'graded_at' => 'datetime',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(GiftQuestion::class, 'topic_gift_question_id');
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'topic_gift_quiz_attempt_id');
    }

    protected static function newFactory(): AttemptAnswerFactory
    {
        return AttemptAnswerFactory::new();
    }
}
