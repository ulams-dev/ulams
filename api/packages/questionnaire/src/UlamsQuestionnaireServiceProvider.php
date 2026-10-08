<?php

namespace Ulams\Questionnaire;

use Ulams\Questionnaire\Repository\Contracts\QuestionAnswerRepositoryContract;
use Ulams\Questionnaire\Repository\Contracts\QuestionnaireModelRepositoryContract;
use Ulams\Questionnaire\Repository\Contracts\QuestionnaireModelTypeRepositoryContract;
use Ulams\Questionnaire\Repository\Contracts\QuestionnaireRepositoryContract;
use Ulams\Questionnaire\Repository\Contracts\QuestionRepositoryContract;
use Ulams\Questionnaire\Repository\QuestionAnswerRepository;
use Ulams\Questionnaire\Repository\QuestionnaireModelRepository;
use Ulams\Questionnaire\Repository\QuestionnaireModelTypeRepository;
use Ulams\Questionnaire\Repository\QuestionnaireRepository;
use Ulams\Questionnaire\Repository\QuestionRepository;
use Ulams\Questionnaire\Services\Contracts\QuestionnaireAnswerServiceContract;
use Ulams\Questionnaire\Services\Contracts\QuestionnaireModelServiceContract;
use Ulams\Questionnaire\Services\Contracts\QuestionnaireServiceContract;
use Ulams\Questionnaire\Services\Contracts\QuestionServiceContract;
use Ulams\Questionnaire\Services\QuestionnaireAnswerService;
use Ulams\Questionnaire\Services\QuestionnaireModelService;
use Ulams\Questionnaire\Services\QuestionnaireService;
use Ulams\Questionnaire\Services\QuestionService;
use Illuminate\Support\ServiceProvider;
use Ulams\Questionnaire\Providers\SettingsServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsQuestionnaireServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'ulams_questionnaires';

    public $bindings = [
        QuestionnaireAnswerServiceContract::class => QuestionnaireAnswerService::class,
        QuestionnaireModelServiceContract::class => QuestionnaireModelService::class,
        QuestionnaireServiceContract::class => QuestionnaireService::class,
        QuestionServiceContract::class => QuestionService::class,
        QuestionAnswerRepositoryContract::class => QuestionAnswerRepository::class,
        QuestionnaireRepositoryContract::class => QuestionnaireRepository::class,
        QuestionRepositoryContract::class => QuestionRepository::class,
        QuestionnaireModelTypeRepositoryContract::class => QuestionnaireModelTypeRepository::class,
        QuestionnaireModelRepositoryContract::class => QuestionnaireModelRepository::class,
    ];

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'questionnaire');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    public function register()
    {
        parent::register();

        $this->app->register(AuthServiceProvider::class);
        $this->app->register(SettingsServiceProvider::class);
    }

    protected function bootForConsole(): void
    {
        $this->publishes([
            __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
        ], self::CONFIG_KEY . '.config');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
