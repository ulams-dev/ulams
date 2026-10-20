<?php

namespace Ulams\Compliance\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Mail;
use Ulams\Compliance\Mail\ScheduledReportMail;
use Ulams\Compliance\Models\ReportSchedule;
use Ulams\Core\Models\User;

/**
 * Sends the schedules that are due. Recipients are tenant users (never free-text addresses), so a
 * report cannot be mailed out of the tenant by typo or by a compromised admin form.
 */
class ScheduledReportService
{
    public function __construct(private ComplianceReportService $reports)
    {
    }

    /** @return int number of schedules sent */
    public function run(CarbonInterface $now): int
    {
        $sent = 0;
        ReportSchedule::query()->where('active', true)->where('next_run_at', '<=', $now)->orderBy('id')->each(function (ReportSchedule $schedule) use ($now, &$sent) {
            try {
                $sent += (int) $this->send($schedule);
            } finally {
                // always move on, so one failing schedule cannot block the others or repeat endlessly
                $schedule->forceFill(['next_run_at' => ReportSchedule::nextRun($schedule->frequency, $now)])->save();
            }
        });

        return $sent;
    }

    public function send(ReportSchedule $schedule): bool
    {
        $recipients = User::query()->whereIn('id', $schedule->recipient_ids ?? [])->whereNotNull('email')->get();
        if ($recipients->isEmpty() || !$this->reports->available($schedule->dataset)) {
            return false;
        }

        $stream = fopen('php://temp', 'w+');
        $rows = $this->reports->write($schedule->dataset, $schedule->filters ?? [], $schedule->format, $stream, null);
        rewind($stream);
        $contents = (string) stream_get_contents($stream);
        fclose($stream);

        $name = sprintf('compliance-%s-%s.%s', $schedule->dataset, now()->format('Ymd'), $schedule->format);
        $mime = $schedule->format === 'csv' ? 'text/csv' : 'application/x-ndjson';
        foreach ($recipients as $recipient) {
            Mail::to($recipient->email)->send(new ScheduledReportMail($schedule->name, $name, $contents, $mime, $rows));
        }
        $schedule->forceFill(['last_sent_at' => now()])->save();

        return true;
    }
}
