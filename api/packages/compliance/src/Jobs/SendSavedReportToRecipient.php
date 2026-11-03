<?php

namespace Ulams\Compliance\Jobs;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Ulams\Compliance\Mail\ScheduledReportMail;
use Ulams\Compliance\Models\ReportSchedule;
use Ulams\Core\Models\User;
use Ulams\Reporting\Definition\InvalidReportDefinition;
use Ulams\Reporting\Export\ReportExporter;
use Ulams\Reporting\Models\SavedReport;
use Ulams\Reporting\Support\ReportContext;

/**
 * Runs a scheduled saved report for ONE recipient, with that recipient's permissions and scope, and mails them the file.
 * One job per recipient, so a large report neither blocks the daily run nor is built twice for the same person.
 */
class SendSavedReportToRecipient implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(public int $scheduleId, public int $userId)
    {
    }

    public function handle(ReportExporter $exporter): void
    {
        $schedule = ReportSchedule::query()->find($this->scheduleId);
        $report = $schedule?->saved_report_id ? SavedReport::query()->find($schedule->saved_report_id) : null;
        $recipient = User::query()->whereNotNull('email')->find($this->userId);
        if ($schedule === null || $report === null || $recipient === null) {
            return;
        }

        $stream = fopen('php://temp', 'w+');
        try {
            $rows = $exporter->write($report->definition, new ReportContext($recipient), $schedule->format, $stream, $report->id, 'schedule');
        } catch (AuthorizationException|InvalidReportDefinition) {
            // access was lost, or the report broke, between the run and this job: nothing is sent
            fclose($stream);

            return;
        }
        rewind($stream);
        $contents = (string) stream_get_contents($stream);
        fclose($stream);

        $name = sprintf('%s-%s.%s', str($report->name)->slug('-')->toString() ?: 'report', now()->format('Ymd'), $schedule->format);
        Mail::to($recipient->email)->send(new ScheduledReportMail($schedule->name, $name, $contents, ReportExporter::MIME[$schedule->format], $rows));
        $schedule->forceFill(['last_sent_at' => now()])->save();
    }
}
