<?php

namespace Ulams\Compliance\Events;

use Ulams\Core\Models\User;
use Ulams\Courses\Events\Course as CourseEvent;
use Ulams\Courses\Models\Course;
use Ulams\TemplatesPdf\Models\FabricPDF;

/**
 * A certificate expires within the configured reminder window. The recipient is the holder.
 */
class CertificateExpiringSoon extends CourseEvent
{
    public function __construct(User $user, Course $course, public FabricPDF $certificate, public int $daysLeft)
    {
        parent::__construct($user, $course);
    }
}
