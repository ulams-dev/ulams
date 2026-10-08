<?php

namespace Ulams\TemplatesSms\Providers;

use Ulams\Consultations\Events\ApprovedTerm;
use Ulams\Consultations\Events\ApprovedTermWithTrainer;
use Ulams\Consultations\Events\RejectTerm;
use Ulams\Consultations\Events\RejectTermWithTrainer;
use Ulams\Consultations\Events\ReminderAboutTerm;
use Ulams\Consultations\Events\ReminderTrainerAboutTerm;
use Ulams\Consultations\Events\ReportTerm;
use Ulams\Templates\Facades\Template;
use Ulams\TemplatesSms\Consultations\ReminderTrainerAboutTermVariables;
use Ulams\TemplatesSms\Consultations\ApprovedTermVariables;
use Ulams\TemplatesSms\Consultations\ApprovedTermWithTrainerVariables;
use Ulams\TemplatesSms\Consultations\RejectTermVariables;
use Ulams\TemplatesSms\Consultations\RejectTermWithTrainerVariables;
use Ulams\TemplatesSms\Consultations\ReportTermVariables;
use Ulams\TemplatesSms\Core\SmsChannel;
use Illuminate\Support\ServiceProvider;

class ConsultationTemplatesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Template::register(ApprovedTermWithTrainer::class, SmsChannel::class, ApprovedTermWithTrainerVariables::class);
        Template::register(ReportTerm::class, SmsChannel::class, ReportTermVariables::class);
        Template::register(RejectTermWithTrainer::class, SmsChannel::class, RejectTermWithTrainerVariables::class);
        Template::register(RejectTerm::class, SmsChannel::class, RejectTermVariables::class);
        Template::register(ApprovedTerm::class, SmsChannel::class, ApprovedTermVariables::class);
        Template::register(ReminderAboutTerm::class, SmsChannel::class, ReportTermVariables::class);
        Template::register(ReminderTrainerAboutTerm::class, SmsChannel::class, ReminderTrainerAboutTermVariables::class);
    }
}
