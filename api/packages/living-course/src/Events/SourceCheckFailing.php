<?php

namespace Ulams\LivingCourse\Events;

/** A connection failed its check three times in a row. In-app and e-mail. Recipient: the session author. */
final class SourceCheckFailing extends LivingCourseEvent
{
}
