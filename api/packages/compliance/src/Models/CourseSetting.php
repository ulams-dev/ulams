<?php

namespace Ulams\Compliance\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Compliance policy of one course: validity of its certificate, recertification window, due dates
 * and the reminder and escalation schedules (null falls back to the package config).
 *
 * @property int $course_id
 * @property bool $mandatory
 * @property int|null $validity_days
 * @property int|null $recertify_before_days
 * @property int|null $default_due_days
 * @property array|null $reminder_days
 * @property array|null $escalation_days
 */
class CourseSetting extends Model
{
    protected $table = 'compliance_course_settings';

    protected $guarded = ['id'];

    protected $casts = [
        'course_id' => 'integer',
        'mandatory' => 'boolean',
        'validity_days' => 'integer',
        'recertify_before_days' => 'integer',
        'default_due_days' => 'integer',
        'reminder_days' => 'array',
        'escalation_days' => 'array',
    ];

    /** @return list<int> days before a date, largest first */
    public function reminderDays(): array
    {
        return self::days($this->reminder_days ?? config('ulams_compliance.reminder_days', []), true);
    }

    /** @return list<int> days after the due date, smallest first */
    public function escalationDays(): array
    {
        return self::days($this->escalation_days ?? config('ulams_compliance.escalation_days', []), false);
    }

    public function recertifyBeforeDays(): ?int
    {
        return $this->recertify_before_days ?? config('ulams_compliance.recertify_before_days');
    }

    /** Defaults of a course that has no row. */
    public static function defaults(int $courseId): self
    {
        return new self(['course_id' => $courseId, 'mandatory' => false]);
    }

    /** @return list<int> */
    public static function days(array $days, bool $descending): array
    {
        $days = array_values(array_unique(array_filter(array_map('intval', $days), fn (int $d) => $d >= 0)));
        $descending ? rsort($days) : sort($days);

        return $days;
    }
}
