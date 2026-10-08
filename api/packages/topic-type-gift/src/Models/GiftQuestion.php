<?php

namespace EscolaLms\TopicTypeGift\Models;

use EscolaLms\Categories\Models\Category;
use EscolaLms\TopicTypeGift\Database\Factories\GiftQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * EscolaLms\TopicTypeGift\Models\GiftQuestion
 *
 * @property int $id
 * @property int $topic_gift_quiz_id
 * @property string $value
 * @property string $type
 * @property int $score
 * @property int $order
 * @property ?int $category_id
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
    ];

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
