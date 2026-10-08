<?php

namespace EscolaLms\TemplatesEmail\Providers;

use EscolaLms\TemplatesEmail\Core\EmailChannel;
use EscolaLms\TemplatesEmail\Youtube\YtProblemVariables;
use EscolaLms\Youtube\Events\YtProblem;
use Illuminate\Support\ServiceProvider;
use EscolaLms\Templates\Facades\Template;

class YoutubeTemplatesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(YtProblem::class, EmailChannel::class, YtProblemVariables::class);
    }
}
