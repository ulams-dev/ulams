<?php

namespace Ulams\Courses\Models;

use Ulams\Courses\Database\Factories\LessonFactory;
use Ulams\ModelFields\Traits\ModelFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @OA\Schema(
 *      schema="Lesson",
 *      required={"title", "order", "course_id"},
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          type="integer",
 *      ),
 *      @OA\Property(
 *          property="title",
 *          description="title",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="duration",
 *          description="duration",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="summary",
 *          description="summary",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="order",
 *          description="order",
 *          type="integer",
 *      ),
 *      @OA\Property(
 *          property="course_id",
 *          description="course_id",
 *          type="integer",
 *      ),
 *      @OA\Property(
 *          property="parent_lesson_id",
 *          description="parent_lesson_id",
 *          type="integer",
 *      ),
 *      @OA\Property(
 *          property="active_from",
 *          description="active_from",
 *          type="string",
 *          format="date-time"
 *      ),
 *      @OA\Property(
 *          property="active_to",
 *          description="active_to",
 *          type="string",
 *          format="date-time"
 *      ),
 * )
 *
 * @property bool $active
 * @property Carbon $active_from
 * @property Carbon $active_to
 * @property int $course_id
 * @property int $order
 * @property int $parent_lesson_id
 * @property string $title
 * @property string $summary
 * @property string $duration
 * @property-read \Illuminate\Database\Eloquent\Collection|\Ulams\Courses\Models\Topic[] $topics
 * @property-read \Illuminate\Database\Eloquent\Collection|\Ulams\Courses\Models\Lesson[] $lessons
 * @property-read \Ulams\Courses\Models\Lesson|null $parentLesson
 */
class Lesson extends Model
{
    use HasFactory;
    use ModelFields;

    public $table = 'lessons';

    public $fillable = [
        'title',
        'duration',
        'order',
        'course_id',
        'summary',
        'active',
        'parent_lesson_id',
        'active_from',
        'active_to',
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'title' => 'string',
        'duration' => 'string',
        'order' => 'integer',
        'course_id' => 'integer',
        'summary' => 'string',
        'active' => 'boolean',
        'parent_lesson_id' => 'integer',
        'active_from' => 'datetime',
        'active_to' => 'datetime',
    ];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [
        'title' => 'required|string|max:255',
        'duration' => 'nullable|string|max:255',
        'order' => 'required|integer',
        'course_id' => 'required|exists:courses,id',
        'summary' => 'nullable|string',
        'active' => 'boolean',
        'parent_lesson_id' => 'nullable|exists:lessons,id',
        'active_from' => 'nullable|date',
        'active_to' => 'nullable|date|after:active_from',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class, 'lesson_id');
    }

    public function parentLesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'parent_lesson_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'parent_lesson_id');
    }

    protected static function newFactory(): LessonFactory
    {
        return \Ulams\Courses\Database\Factories\LessonFactory::new();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('lessons.active', '=', true);
    }

    /**
     * Eager-loads what program resources render: topics with their content and resources, for
     * this lesson and nested lessons down to $depth levels.
     */
    public function scopeWithProgram(Builder $query, int $depth = 4): Builder
    {
        $with = [];
        $prefix = '';
        for ($level = 0; $level < $depth; $level++) {
            $with[] = $prefix . 'topics.topicable';
            $with[] = $prefix . 'topics.resources';
            $prefix .= 'lessons.';
        }

        return $query->with($with);
    }

    public function scopeMain(Builder $query): Builder
    {
        return $query->whereNull('parent_lesson_id');
    }

    public function isActive(): bool
    {
        if (!$this->active ||
            ($this->active_from && $this->active_from >= Carbon::now()) ||
            ($this->active_to && $this->active_to <= Carbon::now())
        ) {
            return false;
        }

        if ($this->parentLesson) {
            return $this->parentLesson->isActive();
        }

        return true;
    }
}
