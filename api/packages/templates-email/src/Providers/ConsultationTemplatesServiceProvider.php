<?php

namespace Ulams\TemplatesEmail\Providers;

use Ulams\Consultations\Events\ApprovedTerm;
use Ulams\Consultations\Events\ApprovedTermWithTrainer;
use Ulams\Consultations\Events\ChangeTerm;
use Ulams\Consultations\Events\RejectTerm;
use Ulams\Consultations\Events\RejectTermWithTrainer;
use Ulams\Consultations\Events\ReminderAboutTerm;
use Ulams\Consultations\Events\ReminderTrainerAboutTerm;
use Ulams\Consultations\Events\ReportTerm;
use Ulams\TemplatesEmail\Consultations\ApprovedTermVariables;
use Ulams\TemplatesEmail\Consultations\ApprovedTermWithTrainerVariables;
use Ulams\TemplatesEmail\Consultations\ChangeTermVariables;
use Ulams\TemplatesEmail\Consultations\RejectTermVariables;
use Ulams\TemplatesEmail\Consultations\RejectTermWithTrainerVariables;
use Ulams\TemplatesEmail\Consultations\ReminderAboutTermVariables;
use Ulams\TemplatesEmail\Consultations\ReminderTrainerAboutTermVariables;
use Ulams\TemplatesEmail\Consultations\ReportTermVariables;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Illuminate\Support\ServiceProvider;
use Ulams\Templates\Facades\Template;

class ConsultationTemplatesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(ApprovedTermWithTrainer::class, EmailChannel::class, ApprovedTermWithTrainerVariables::class);
        Template::register(ApprovedTerm::class, EmailChannel::class, ApprovedTermVariables::class);
        Template::register(RejectTermWithTrainer::class, EmailChannel::class, RejectTermWithTrainerVariables::class);
        Template::register(RejectTerm::class, EmailChannel::class, RejectTermVariables::class);
        Template::register(ReportTerm::class, EmailChannel::class, ReportTermVariables::class);
        Template::register(ReminderAboutTerm::class, EmailChannel::class, ReminderAboutTermVariables::class);
        Template::register(ReminderTrainerAboutTerm::class, EmailChannel::class, ReminderTrainerAboutTermVariables::class);
        Template::register(ChangeTerm::class, EmailChannel::class, ChangeTermVariables::class);
    }
}
