<?php

namespace Ulams\TemplatesEmail\Providers;

use Ulams\Templates\Facades\Template;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\Video\VideoProcessFailedVariables;
use Ulams\TemplatesEmail\Video\VideoProcessFinishedVariables;
use Ulams\TemplatesEmail\Video\VideoProcessStartedVariables;
use Ulams\TemplatesEmail\Video\VideoProcessStateVariables;
use Ulams\Video\Events\ProcessVideoFailed;
use Ulams\Video\Events\ProcessVideoFinished;
use Ulams\Video\Events\ProcessVideoStarted;
use Ulams\Video\Events\ProcessVideoState;
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
