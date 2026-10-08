<?php

namespace EscolaLms\TopicTypeGift\Models;

use EscolaLms\TopicTypeGift\Database\Factories\GiftQuizFactory;
use EscolaLms\TopicTypeGift\Events\QuizGradabilityChangedEvent;
use EscolaLms\TopicTypes\Models\TopicContent\AbstractTopicContent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @OA\Schema(
 *      schema="TopicGiftQuiz",
 *      required={"value"},
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          @OA\Schema(
 *             type="integer",
 *         )
 *      ),
 *      @OA\Property(
 *          property="value",
 *          description="value",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="max_attempts",
 *          description="max_attempts",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="max_execution_time",
 *          description="max execution time in minutes",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="min_pass_score",
 *          description="minimum score to pass the quiz",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="counts_to_grade",
 *          description="whether the quiz counts towards the final grade",
 *          type="boolean"
 *      ),
 *      @OA\Property(
 *          property="weight",
 *          description="weight of the grade this quiz produces in the journal (dziennik ocen)",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="randomize_order",
 *          description="whether the question order is randomized per attempt",
 *          type="boolean"
 *      )
 * )
 */

/**
 * EscolaLms\TopicTypeGift\Models\GiftQuiz
 *
 * @property int $id
 * @property string $value
 * @property ?integer $max_attempts
 * @property ?integer $max_execution_time
 * @property ?double $min_pass_score
 * @property bool $counts_to_grade
 * @property int $weight
 * @property bool $randomize_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Collection|GiftQuestion[] $questions
 */
class GiftQuiz extends AbstractTopicContent
{
    use HasFactory;

    public $table = 'topic_gift_quizzes';

    protected $fillable = [
        'value',
        'max_attempts',
        'max_execution_time',
        'min_pass_score',
        'counts_to_grade',
        'weight',
        'randomize_order',
    ];

    protected $casts = [
        'id' => 'integer',
        'value' => 'string',
        'max_attempts' => 'integer',
        'max_execution_time' => 'integer',
        'min_pass_score' => 'double',
        'counts_to_grade' => 'boolean',
        'weight' => 'integer',
        'randomize_order' => 'boolean',
    ];

    public static function rules(): array
    {
        return [
            'value' => ['required', 'string'],
            'max_attempts' => ['nullable', 'integer', 'min:1'],
            'max_execution_time' => ['nullable', 'integer', 'min:1'],
            'min_pass_score' => ['nullable', 'numeric', 'min:0'],
            'counts_to_grade' => ['boolean'],
            'weight' => ['integer', 'min:0', 'max:100'],
            'randomize_order' => ['boolean'],
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(GiftQuestion::class, 'topic_gift_quiz_id');
    }

    protected static function newFactory(): GiftQuizFactory
    {
        return GiftQuizFactory::new();
    }

    public function getMorphClass()
    {
        return self::class;
    }

    protected static function booted(): void
    {
        parent::booted();

        static::updated(function (GiftQuiz $quiz) {
            if ($quiz->wasChanged('counts_to_grade')) {
                event(new QuizGradabilityChangedEvent($quiz));
            }
        });
    }
}
