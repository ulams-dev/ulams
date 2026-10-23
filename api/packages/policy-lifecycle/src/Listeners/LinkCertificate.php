<?php

namespace Ulams\PolicyLifecycle\Listeners;

use Ulams\Courses\Models\Course;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyCertificate;
use Ulams\PolicyLifecycle\Models\PolicyVariant;
use Ulams\TemplatesPdf\Events\PdfCreated;

/**
 * A certificate for a department's course, or for its exam course, is tied to the policy version in force when it was issued (ADR 0140),
 * in the package's own table so templates-pdf stays unchanged. Courses that are not policy variants are ignored.
 */
final class LinkCertificate
{
    public function handle(PdfCreated $event): void
    {
        $pdf = $event->getPdf();
        if ($pdf->assignable_type !== Course::class || !$pdf->assignable_id || !$pdf->user_id) {
            return;
        }
        $courseId = (int) $pdf->assignable_id;
        $variant = PolicyVariant::query()->where('course_id', $courseId)->orWhere('exam_course_id', $courseId)->first();
        $policy = $variant !== null ? Policy::query()->find($variant->policy_id) : null;
        if ($variant === null || $policy === null || $policy->current_version_id === null) {
            return;
        }
        PolicyCertificate::query()->firstOrCreate(
            ['fabric_pdf_id' => $pdf->getKey()],
            ['policy_version_id' => $policy->current_version_id, 'variant_id' => $variant->id, 'user_id' => (int) $pdf->user_id],
        );
    }
}
