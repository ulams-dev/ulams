<?php

namespace Ulams\TopicTypeGift\Models;

use Ulams\Categories\Models\Category;
use Ulams\TopicTypeGift\Database\Factories\GiftQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ulams\TopicTypeGift\Models\GiftQuestion
 *
 * @property int $id
 * @property int $topic_gift_quiz_id
 * @property string $value
 * @property string $type
 * @property int $score
 * @property int $order
 * @property ?int $category_id
 * @property ?Carbon $archived_at set when the question was retired but learners answered it
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read GiftQuiz $giftQuiz
 * @property-read ?Category $category
 */
class GiftQuestion extends Model
{
    use HasFactory;

    public $table = 'topic_gift_questions';

    public $fillable = [
        'topic_gift_quiz_id',
        'value',
        'type',
        'score',
        'order',
        'category_id',
        'archived_at',
    ];

    public $casts = [
        'archived_at' => 'datetime',
    ];

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function giftQuiz(): BelongsTo
    {
        return $this->belongsTo(GiftQuiz::class, 'topic_gift_quiz_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected static function booted(): void
    {
        static::creating(function (GiftQuestion $question) {
            if (!$question->order) {
                $question->order = 1 + (int) GiftQuestion::where('topic_gift_quiz_id', $question->topic_gift_quiz_id)
                        ->max('order');
            }
        });
    }

    protected static function newFactory(): GiftQuestionFactory
    {
        return GiftQuestionFactory::new();
    }
}
