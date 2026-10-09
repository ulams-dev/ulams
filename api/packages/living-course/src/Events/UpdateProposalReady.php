<?php

namespace Ulams\LivingCourse\Events;

/** An update proposal is ready for review or waits for the author to start the analysis. In-app and e-mail. Recipients: the session author and the course authors who may review. */
final class UpdateProposalReady extends LivingCourseEvent
{
}
