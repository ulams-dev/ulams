<?php

namespace EscolaLms\CourseAccess\Models;

use EscolaLms\Core\Models\User;
use EscolaLms\CourseAccess\Database\Factories\CourseAccessEnquiryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EscolaLms\CourseAccess\Models\CourseAccessEnquiry
 *
 * @property-read int $id
 * @property int $course_id
 * @property int $user_id
 * @property string $status
 * @property array $data
 *
 * @property Course $course
 * @property User $user
 */
class CourseAccessEnquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'user_id',
        'status',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function newFactory(): CourseAccessEnquiryFactory
    {
        return CourseAccessEnquiryFactory::new();
    }
}
