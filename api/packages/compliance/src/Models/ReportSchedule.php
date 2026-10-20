<?php

namespace Ulams\Compliance\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A compliance dataset e-mailed to tenant users on a schedule.
 *
 * @property int $id
 * @property string $name
 * @property string $dataset
 * @property array|null $filters
 * @property string $frequency daily, weekly or monthly
 * @property string $format csv or jsonl
 * @property list<int> $recipient_ids
 * @property bool $active
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $next_run_at
 * @property \Illuminate\Support\Carbon|null $last_sent_at
 */
class ReportSchedule extends Model
{
    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    protected $table = 'compliance_report_schedules';

    protected $guarded = ['id'];

    protected $casts = ['filters' => 'array', 'recipient_ids' => 'array', 'active' => 'boolean', 'next_run_at' => 'datetime', 'last_sent_at' => 'datetime'];

    /** The run after `$from`: tomorrow, next week or next month at 06:00. */
    public static function nextRun(string $frequency, \DateTimeInterface $from): \Illuminate\Support\Carbon
    {
        $at = \Illuminate\Support\Carbon::instance($from)->startOfDay()->setTime(6, 0);

        return match ($frequency) {
            'weekly' => $at->addWeek(),
            'monthly' => $at->addMonthNoOverflow(),
            default => $at->addDay(),
        };
    }
}
