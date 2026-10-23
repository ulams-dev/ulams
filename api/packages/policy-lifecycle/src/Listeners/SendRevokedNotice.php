<?php

namespace Ulams\PolicyLifecycle\Listeners;

use Ulams\Compliance\Services\NotificationLog;
use Ulams\Courses\Models\Course;
use Ulams\PolicyLifecycle\Events\CertificateRevokedNotice;
use Ulams\PolicyLifecycle\Services\RolloutService;
use Ulams\TemplatesPdf\Events\CertificateRevoked;

/**
 * A revoked course certificate is told to its holder, once. The certificates an approved policy change supersedes are
 * skipped: the retraining notice of the same change says so already.
 */
final class SendRevokedNotice
{
    public function __construct(private readonly NotificationLog $log)
    {
    }

    public function handle(CertificateRevoked $event): void
    {
        $pdf = $event->pdf;
        $reason = (string) $pdf->revoked_reason;
        if ($pdf->assignable_type !== Course::class || !$pdf->user || str_starts_with($reason, RolloutService::REASON_PREFIX)) {
            return;
        }
        $course = Course::query()->find($pdf->assignable_id);
        if ($course === null) {
            return;
        }
        $this->log->once('certificate_revoked', 'certificate', (int) $pdf->getKey(), 0, (int) $pdf->user_id, fn () => event(new CertificateRevokedNotice($pdf->user, $course, $pdf, $reason !== '' ? $reason : 'No reason was given.')));
    }
}
