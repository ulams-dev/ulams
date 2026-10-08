<?php

namespace Ulams\TemplatesEmail\Providers;

use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\Youtube\YtProblemVariables;
use Ulams\Youtube\Events\YtProblem;
use Illuminate\Support\ServiceProvider;
use Ulams\Templates\Facades\Template;

class YoutubeTemplatesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(YtProblem::class, EmailChannel::class, YtProblemVariables::class);
    }
}
