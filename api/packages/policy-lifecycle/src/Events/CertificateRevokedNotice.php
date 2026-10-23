<?php

namespace Ulams\PolicyLifecycle\Events;

use Ulams\Core\Models\User;
use Ulams\Courses\Events\Course as CourseEvent;
use Ulams\Courses\Models\Course;
use Ulams\TemplatesPdf\Models\FabricPDF;

/**
 * Tells the holder that a certificate of theirs was revoked, with the reason the revoker gave. Not sent for the
 * certificates a policy change supersedes: that learner gets the retraining notice, which says so.
 */
class CertificateRevokedNotice extends CourseEvent
{
    public function __construct(User $user, Course $course, public FabricPDF $certificate, public string $reason)
    {
        parent::__construct($user, $course);
    }
}
