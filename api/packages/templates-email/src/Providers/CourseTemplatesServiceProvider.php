<?php

namespace EscolaLms\TemplatesEmail\Providers;

use EscolaLms\Courses\Events\CourseAccessFinished;
use EscolaLms\Courses\Events\CourseAccessStarted;
use EscolaLms\Courses\Events\CourseAssigned;
use EscolaLms\Courses\Events\CourseDeadlineSoon;
use EscolaLms\Courses\Events\CoursedPublished;
use EscolaLms\Courses\Events\CourseFinished;
use EscolaLms\Courses\Events\CourseStarted;
use EscolaLms\Courses\Events\CourseUnassigned;
use EscolaLms\Courses\Events\TopicFinished;
use EscolaLms\Templates\Facades\Template;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
use EscolaLms\TemplatesEmail\Courses\AccessFinishedCourseVariables;
use EscolaLms\TemplatesEmail\Courses\AccessStartedCourseVariables;
use EscolaLms\TemplatesEmail\Courses\DeadlineIncomingVariables;
use EscolaLms\TemplatesEmail\Courses\PublishedCourseVariables;
use EscolaLms\TemplatesEmail\Courses\StartedCourseVariables;
use EscolaLms\TemplatesEmail\Courses\TopicFinishedCourseVariables;
use EscolaLms\TemplatesEmail\Courses\UserAssignedToCourseVariables;
use EscolaLms\TemplatesEmail\Courses\UserFinishedCourseVariables;
use EscolaLms\TemplatesEmail\Courses\UserUnassignedFromCourseVariables;
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
