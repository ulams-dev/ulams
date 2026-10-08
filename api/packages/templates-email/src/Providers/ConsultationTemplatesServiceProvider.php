<?php

namespace EscolaLms\TemplatesEmail\Providers;

use EscolaLms\Consultations\Events\ApprovedTerm;
use EscolaLms\Consultations\Events\ApprovedTermWithTrainer;
use EscolaLms\Consultations\Events\ChangeTerm;
use EscolaLms\Consultations\Events\RejectTerm;
use EscolaLms\Consultations\Events\RejectTermWithTrainer;
use EscolaLms\Consultations\Events\ReminderAboutTerm;
use EscolaLms\Consultations\Events\ReminderTrainerAboutTerm;
use EscolaLms\Consultations\Events\ReportTerm;
use EscolaLms\TemplatesEmail\Consultations\ApprovedTermVariables;
use EscolaLms\TemplatesEmail\Consultations\ApprovedTermWithTrainerVariables;
use EscolaLms\TemplatesEmail\Consultations\ChangeTermVariables;
use EscolaLms\TemplatesEmail\Consultations\RejectTermVariables;
use EscolaLms\TemplatesEmail\Consultations\RejectTermWithTrainerVariables;
use EscolaLms\TemplatesEmail\Consultations\ReminderAboutTermVariables;
use EscolaLms\TemplatesEmail\Consultations\ReminderTrainerAboutTermVariables;
use EscolaLms\TemplatesEmail\Consultations\ReportTermVariables;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
use Illuminate\Support\ServiceProvider;
use EscolaLms\Templates\Facades\Template;

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
