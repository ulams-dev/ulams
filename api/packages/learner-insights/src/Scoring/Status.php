<?php

namespace Ulams\LearnerInsights\Scoring;

/** Status of a learner in a course, or of one element. Never an input to grades, access or certificates (ADR 0061). */
final class Status
{
    public const ON_TRACK = 'on_track';
    public const STRUGGLING = 'struggling';
    public const AT_RISK = 'at_risk';

    public const ALL = [self::ON_TRACK, self::STRUGGLING, self::AT_RISK];
}
