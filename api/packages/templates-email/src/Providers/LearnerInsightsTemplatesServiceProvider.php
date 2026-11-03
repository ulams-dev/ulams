<?php

namespace Ulams\TemplatesEmail\Providers;

use Illuminate\Support\ServiceProvider;
use Ulams\LearnerInsights\Events\LearnerNudged;
use Ulams\Templates\Facades\Template;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\LearnerInsights\LearnerNudgedVariables;

/** E-mail template of Learner Insights (the only event of the package that carries a user). */
class LearnerInsightsTemplatesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Template::register(LearnerNudged::class, EmailChannel::class, LearnerNudgedVariables::class);
    }
}
