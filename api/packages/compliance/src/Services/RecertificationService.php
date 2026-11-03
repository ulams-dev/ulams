<?php

namespace Ulams\Compliance\Services;

use Carbon\CarbonInterface;
use Ulams\Compliance\Enums\AssignmentSource;
use Ulams\Compliance\Models\Assignment;
use Ulams\Courses\Models\Course;
use Ulams\TemplatesPdf\Models\FabricPDF;

/**
 * Starts a recertification assignment, due on the expiry date, for every current certificate that
 * is inside its course's recertification window. Courses opt in by having compliance settings;
 * `recertify_before_days = 0` turns it off for a course.
 */
class RecertificationService
{
    public function __construct(private CourseSettings $settings, private AssignmentService $assignments)
    {
    }

    /** @return int number of recertification assignments created */
    public function run(CarbonInterface $now): int
    {
        $count = 0;

        FabricPDF::query()
            ->where('assignable_type', Course::class)
            ->whereNull('revoked_at')
            ->whereNull('superseded_by_id')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->orderBy('id')
            ->each(function (FabricPDF $certificate) use ($now, &$count) {
                $courseId = (int) $certificate->assignable_id;
                if (!$this->settings->has($courseId)) {
                    return;
                }
                $window = $this->settings->for($courseId)->recertifyBeforeDays();
                if (!$window || $certificate->expires_at->gt($now->copy()->addDays($window))) {
                    return;
                }
                if (Assignment::query()->where('recertifies_certificate_id', $certificate->getKey())->exists()) {
                    return;
                }
                $course = Course::find($courseId);
                if (!$course || !$certificate->user) {
                    return;
                }

                $this->assignments->assign($certificate->user, $course, $certificate->expires_at, AssignmentSource::RECERTIFICATION, null, $certificate);
                $count++;
            });

        return $count;
    }
}
