<?php

namespace Ulams\TemplatesEmail\Providers;

use Illuminate\Support\ServiceProvider;
use Ulams\LivingCourse\Events\CourseContentUpdated;
use Ulams\LivingCourse\Events\SourceCheckFailing;
use Ulams\LivingCourse\Events\UpdateProposalReady;
use Ulams\Templates\Facades\Template;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\LivingCourse\CourseContentUpdatedVariables;
use Ulams\TemplatesEmail\LivingCourse\SourceCheckFailingVariables;
use Ulams\TemplatesEmail\LivingCourse\UpdateProposalReadyVariables;

/** E-mail templates of Living Course (the other events are in-app only). */
class LivingCourseTemplatesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Template::register(UpdateProposalReady::class, EmailChannel::class, UpdateProposalReadyVariables::class);
        Template::register(SourceCheckFailing::class, EmailChannel::class, SourceCheckFailingVariables::class);
        Template::register(CourseContentUpdated::class, EmailChannel::class, CourseContentUpdatedVariables::class);
    }
}
