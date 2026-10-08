<?php

namespace EscolaLms\TemplatesEmail\Providers;

use EscolaLms\Templates\Facades\Template;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
use EscolaLms\TemplatesEmail\Video\VideoProcessFailedVariables;
use EscolaLms\TemplatesEmail\Video\VideoProcessFinishedVariables;
use EscolaLms\TemplatesEmail\Video\VideoProcessStartedVariables;
use EscolaLms\TemplatesEmail\Video\VideoProcessStateVariables;
use EscolaLms\Video\Events\ProcessVideoFailed;
use EscolaLms\Video\Events\ProcessVideoFinished;
use EscolaLms\Video\Events\ProcessVideoStarted;
use EscolaLms\Video\Events\ProcessVideoState;
use Illuminate\Support\ServiceProvider;

class VideoTemplatesServiceProvider extends ServiceProvider
{

    public function boot(): void
    {
        Template::register(
            ProcessVideoStarted::class,
            EmailChannel::class,
            VideoProcessStartedVariables::class
        );
        Template::register(
            ProcessVideoFailed::class,
            EmailChannel::class,
            VideoProcessFailedVariables::class
        );
        Template::register(
            ProcessVideoFinished::class,
            EmailChannel::class,
            VideoProcessFinishedVariables::class
        );
        Template::register(
            ProcessVideoState::class,
            EmailChannel::class,
            VideoProcessStateVariables::class
        );
    }
}
