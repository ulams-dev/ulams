<?php

namespace Ulams\TemplatesEmail\Providers;

use Ulams\Courses\Events\CourseAccessFinished;
use Ulams\Courses\Events\CourseAccessStarted;
use Ulams\Courses\Events\CourseAssigned;
use Ulams\Courses\Events\CourseDeadlineSoon;
use Ulams\Courses\Events\CoursedPublished;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Events\CourseStarted;
use Ulams\Courses\Events\CourseUnassigned;
use Ulams\Courses\Events\TopicFinished;
use Ulams\Templates\Facades\Template;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\Courses\AccessFinishedCourseVariables;
use Ulams\TemplatesEmail\Courses\AccessStartedCourseVariables;
use Ulams\TemplatesEmail\Courses\DeadlineIncomingVariables;
use Ulams\TemplatesEmail\Courses\PublishedCourseVariables;
use Ulams\TemplatesEmail\Courses\StartedCourseVariables;
use Ulams\TemplatesEmail\Courses\TopicFinishedCourseVariables;
use Ulams\TemplatesEmail\Courses\UserAssignedToCourseVariables;
use Ulams\TemplatesEmail\Courses\UserFinishedCourseVariables;
use Ulams\TemplatesEmail\Courses\UserUnassignedFromCourseVariables;
use Illuminate\Support\ServiceProvider;

class CourseTemplatesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(
            CourseDeadlineSoon::class,
            EmailChannel::class,
            DeadlineIncomingVariables::class
        );
        Template::register(
            CourseFinished::class,
            EmailChannel::class,
            UserFinishedCourseVariables::class
        );
        Template::register(
            CourseAssigned::class,
            EmailChannel::class,
            UserAssignedToCourseVariables::class
        );
        Template::register(
            CourseUnassigned::class,
            EmailChannel::class,
            UserUnassignedFromCourseVariables::class
        );
        Template::register(
            CoursedPublished::class,
            EmailChannel::class,
            PublishedCourseVariables::class
        );
        Template::register(
            CourseStarted::class,
            EmailChannel::class,
            StartedCourseVariables::class
        );
        Template::register(
            CourseAccessStarted::class,
            EmailChannel::class,
            AccessStartedCourseVariables::class
        );
        Template::register(
            CourseAccessFinished::class,
            EmailChannel::class,
            AccessFinishedCourseVariables::class
        );
        Template::register(
            TopicFinished::class,
            EmailChannel::class,
            TopicFinishedCourseVariables::class
        );
    }
}
