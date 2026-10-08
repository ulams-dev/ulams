<?php

namespace Ulams\Questionnaire;

use Ulams\Questionnaire\Models\Question;
use Ulams\Questionnaire\Models\Questionnaire;
use Ulams\Questionnaire\Policies\QuestionnairePolicy;
use Ulams\Questionnaire\Policies\QuestionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Questionnaire::class => QuestionnairePolicy::class,
        Question::class => QuestionPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
