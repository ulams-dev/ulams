<?php

namespace Ulams\Compliance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ulams\Compliance\Enums\AssignmentStatus;
use Ulams\Core\Models\User;
use Ulams\Courses\Models\Course;
use Ulams\TemplatesPdf\Models\FabricPDF;

/**
 * One required-training obligation of a user for a course.
 *
 * @property int $id
 * @property int $user_id
 * @property int $course_id
 * @property string $source
 * @property string $status
 * @property \Illuminate\Support\Carbon $assigned_at
 * @property int|null $assigned_by
 * @property \Illuminate\Support\Carbon|null $due_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int|null $certificate_id
 * @property int|null $recertifies_certificate_id
 * @property int $escalation_level
 */
class Assignment extends Model
{
    protected $table = 'compliance_assignments';

    protected $guarded = ['id'];

    protected $casts = [
        'assigned_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'escalation_level' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(FabricPDF::class, 'certificate_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', AssignmentStatus::ASSIGNED);
    }

    public function isOpen(): bool
    {
        return $this->status === AssignmentStatus::ASSIGNED;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_at !== null && $this->due_at->isPast();
    }

    /** assigned, overdue, completed or cancelled: the state a learner and a manager see. */
    public function displayStatus(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->status;
    }
}
