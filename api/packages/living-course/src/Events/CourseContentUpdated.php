<?php

namespace Ulams\LivingCourse\Events;

/** A learner has new notices after an update. In-app and e-mail, at most one e-mail per course per 7 days. Recipient: the learner. */
final class CourseContentUpdated extends LivingCourseEvent
{
}
