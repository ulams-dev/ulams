<?php

namespace Ulams\Courses\Models;

use Ulams\Courses\Database\Factories\CourseUserAttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseUserAttendance extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $dates = ['attendance_date'];

    protected $fillable = [
        'course_progress_id',
        'attendance_date',
        'attempt',
        'seconds',
    ];

    protected $casts = [
        'course_progress_id' => 'int',
        'attendance_date' => 'datetime',
    ];

    public function courseProgress(): BelongsTo
    {
        return $this->belongsTo(CourseProgress::class, 'course_progress_id');
    }

    protected static function newFactory(): CourseUserAttendanceFactory
    {
        return \Ulams\Courses\Database\Factories\CourseUserAttendanceFactory::new();
    }
}
