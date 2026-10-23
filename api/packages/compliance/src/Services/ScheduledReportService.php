<?php

namespace Ulams\Compliance\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Mail;
use Ulams\Compliance\Mail\ScheduledReportMail;
use Ulams\Compliance\Models\ReportSchedule;
use Ulams\Compliance\Jobs\SendSavedReportToRecipient;
use Ulams\Compliance\Mail\ScheduledReportSkippedMail;
use Ulams\Core\Models\User;
use Ulams\Reporting\Models\SavedReport;
use Ulams\Reporting\Services\SavedReports;

/**
 * Sends the schedules that are due. Recipients are tenant users (never free-text addresses), so a
 * report cannot be mailed out of the tenant by typo or by a compromised admin form.
 */
class ScheduledReportService
{
    public function __construct(private ComplianceReportService $reports, private SavedReports $savedReports)
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
        if ($schedule->saved_report_id !== null) {
            return $this->sendSavedReport($schedule);
        }
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

    /**
     * A schedule on a saved report: the report runs once per recipient, with that recipient's own permissions and scope
     * (ADR 0194), in one queued job each. A recipient who cannot run it any more, or has no address, is skipped and the
     * schedule's owner is told who and why; nothing is sent in their place.
     */
    private function sendSavedReport(ReportSchedule $schedule): bool
    {
        $report = SavedReport::query()->find($schedule->saved_report_id);
        $recipients = User::query()->whereIn('id', $schedule->recipient_ids ?? [])->get();
        $skipped = [];
        $queued = 0;
        foreach ($recipients as $recipient) {
            $reason = match (true) {
                $report === null => __('the saved report was deleted'),
                empty($recipient->email) => __('no e-mail address'),
                !$this->savedReports->runnable($report, $recipient) => __('no permission for this report'),
                $this->savedReports->problems($report) !== [] => __('the report needs repair'),
                default => null,
            };
            if ($reason !== null) {
                $skipped[] = ['id' => $recipient->getKey(), 'name' => (string) $recipient->name, 'reason' => (string) $reason];
                continue;
            }
            SendSavedReportToRecipient::dispatch($schedule->getKey(), $recipient->getKey());
            $queued++;
        }
        // recipients who no longer exist are skipped too
        foreach (array_diff($schedule->recipient_ids ?? [], $recipients->modelKeys()) as $missing) {
            $skipped[] = ['id' => (int) $missing, 'name' => '#' . $missing, 'reason' => (string) __('the user no longer exists')];
        }

        $schedule->forceFill(['skipped_recipient_ids' => array_column($skipped, 'id') ?: null])->save();
        $owner = $schedule->created_by ? User::query()->whereNotNull('email')->find($schedule->created_by) : null;
        if ($skipped !== [] && $owner !== null) {
            Mail::to($owner->email)->send(new ScheduledReportSkippedMail($schedule->name, array_map(fn (array $s) => ['name' => $s['name'], 'reason' => $s['reason']], $skipped)));
        }

        return $queued > 0;
    }
}
