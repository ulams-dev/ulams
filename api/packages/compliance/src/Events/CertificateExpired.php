<?php

namespace Ulams\Compliance\Events;

use Ulams\Core\Models\User;
use Ulams\Courses\Events\Course as CourseEvent;
use Ulams\Courses\Models\Course;
use Ulams\TemplatesPdf\Models\FabricPDF;

/**
 * A certificate expired. The recipient is the holder.
 */
class CertificateExpired extends CourseEvent
{
    public function __construct(User $user, Course $course, public FabricPDF $certificate)
    {
        parent::__construct($user, $course);
    }
}
