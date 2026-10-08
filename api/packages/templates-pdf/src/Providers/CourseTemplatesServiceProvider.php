<?php

namespace Ulams\TemplatesPdf\Providers;

use Ulams\Courses\Events\CourseFinished;
use Ulams\Templates\Facades\Template;
use Ulams\TemplatesPdf\Core\PdfChannel;
use Ulams\TemplatesPdf\Courses\UserFinishedCourseVariables;
use Illuminate\Support\ServiceProvider;

class CourseTemplatesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(CourseFinished::class, PdfChannel::class, UserFinishedCourseVariables::class);
    }
}
