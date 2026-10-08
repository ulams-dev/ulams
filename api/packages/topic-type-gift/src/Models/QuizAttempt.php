<?php

namespace EscolaLms\TopicTypeGift\Models;

use EscolaLms\TopicTypeGift\Database\Factories\QuizAttemptFactory;
use EscolaLms\Auth\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * EscolaLms\TopicTypeGift\Models\QuizAttempt
 *
 * @property int $id
 * @property int $topic_gift_quiz_id
 * @property int $user_id
 * @property Carbon $started_at
 * @property Carbon $end_at
 * @property string|null $tutor_feedback
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read GiftQuiz $giftQuiz
 * @property-read AttemptAnswer[]|Collection $answers
 * @property-read User $user
 * @property-read int|float $max_score
 * @property-read int|float|null $result_score
 * @property-read float|null $result_percent
 * @property-read bool|null $is_passed
 * @property-read int|null $correct_answers_count
 *
 */
class QuizAttempt extends Model
{
    use HasFactory;

    public $table = 'topic_gift_quiz_attempts';

    public $fillable = [
        'topic_gift_quiz_id',
        'user_id',
        'started_at',
        'end_at',
        'tutor_feedback',
    ];

    public $casts = [
        'started_at' => 'datetime',
        'end_at' => 'datetime',
        'tutor_feedback' => 'string',
    ];

    public function giftQuiz(): BelongsTo
    {
        return $this->belongsTo(GiftQuiz::class, 'topic_gift_quiz_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class, 'topic_gift_quiz_attempt_id');
    }

    public function isEnded(): bool
    {
        return $this->end_at != null && $this->end_at <= Carbon::now();
    }

    /**
     * Whether every answer of the attempt has been graded
     * (auto-scored answers are graded on submission, essays once a lecturer scores them).
     */
    public function isFullyGraded(): bool
    {
        return !$this->answers()->whereNull('graded_at')->exists();
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('end_at')->orWhere('end_at', '>=', Carbon::now());
    }

    /**
     * Maximum points obtainable in the quiz (sum of question scores).
     *
     * @return int|float
     */
    public function getMaxScoreAttribute()
    {
        return $this->giftQuiz->questions->sum('score');
    }

    /**
     * Points scored in the attempt; null until the attempt is ended.
     *
     * @return int|float|null
     */
    public function getResultScoreAttribute()
    {
        return $this->isEnded() ? $this->answers->sum('score') : null;
    }

    /**
     * Result as a percent of the maximum score, rounded to 2 decimals.
     * Null when not ended or when there is no maximum score.
     */
    public function getResultPercentAttribute(): ?float
    {
        $maxScore = $this->max_score;

        return ($this->isEnded() && $maxScore > 0)
            ? round($this->answers->sum('score') / $maxScore * 100, 2)
            : null;
    }

    /**
     * Whether the attempt reached min_pass_score.
     * Null when not ended or when the quiz has no min_pass_score.
     */
    public function getIsPassedAttribute(): ?bool
    {
        $minPassScore = $this->giftQuiz->min_pass_score;

        return ($this->isEnded() && $minPassScore !== null)
            ? $this->answers->sum('score') >= $minPassScore
            : null;
    }

    /**
     * Number of answers awarded full points (answer.score >= question.score).
     * Null when not ended.
     */
    public function getCorrectAnswersCountAttribute(): ?int
    {
        if (!$this->isEnded()) {
            return null;
        }

        $questionScores = $this->giftQuiz->questions->keyBy('id');

        return $this->answers->filter(function (AttemptAnswer $answer) use ($questionScores) {
            $question = $questionScores->get($answer->topic_gift_question_id);

            return $question !== null && $answer->score >= $question->score;
        })->count();
    }

    protected static function newFactory(): QuizAttemptFactory
    {
        return QuizAttemptFactory::new();
    }
}
