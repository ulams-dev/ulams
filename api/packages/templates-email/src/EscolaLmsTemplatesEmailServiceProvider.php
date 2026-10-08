<?php

namespace EscolaLms\TemplatesEmail;

use EscolaLms\Auth\EscolaLmsAuthServiceProvider;
use EscolaLms\Auth\Events\AccountRegistered;
use EscolaLms\Auth\Events\ForgotPassword;
use EscolaLms\Auth\Listeners\CreatePasswordResetToken;
use EscolaLms\Auth\Listeners\SendEmailVerificationNotification;
use EscolaLms\ConsultationAccess\EscolaLmsConsultationAccessServiceProvider;
use EscolaLms\Consultations\EscolaLmsConsultationsServiceProvider;
use EscolaLms\CourseAccess\EscolaLmsCourseAccessServiceProvider;
use EscolaLms\Settings\Facades\AdministrableConfig;
use EscolaLms\Settings\Models\Setting;
use EscolaLms\Tasks\EscolaLmsTasksServiceProvider;
use EscolaLms\Templates\EscolaLmsTemplatesServiceProvider;
use EscolaLms\Templates\Repository\Contracts\TemplateRepositoryContract;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
use EscolaLms\TemplatesEmail\Observers\SettingObserver;
use EscolaLms\TemplatesEmail\Providers\AssignWithoutAccountTemplatesEventServiceProvider;
use EscolaLms\TemplatesEmail\Providers\AuthTemplatesEventServiceProvider;
use EscolaLms\TemplatesEmail\Providers\AuthTemplatesServiceProvider;
use EscolaLms\TemplatesEmail\Providers\ConsultationAccessTemplatesServiceProvider;
use EscolaLms\TemplatesEmail\Providers\ConsultationTemplatesServiceProvider;
use EscolaLms\TemplatesEmail\Providers\CartTemplatesServiceProvider;
use EscolaLms\TemplatesEmail\Providers\CourseAccessTemplatesServiceProvider;
use EscolaLms\TemplatesEmail\Providers\CourseTemplatesServiceProvider;
use EscolaLms\TemplatesEmail\Providers\CsvUsersTemplatesServiceProvider;
use EscolaLms\TemplatesEmail\Providers\TaskTemplatesServiceProvider;
use EscolaLms\TemplatesEmail\Providers\TemplateServiceProvider;
use EscolaLms\TemplatesEmail\Providers\TopicTypeProjectTemplatesServiceProvider;
use EscolaLms\TemplatesEmail\Providers\VideoTemplatesServiceProvider;
use EscolaLms\TemplatesEmail\Providers\WebinarTemplatesServiceProvider;
use EscolaLms\TemplatesEmail\Providers\YoutubeTemplatesServiceProvider;
use EscolaLms\TemplatesEmail\Rules\MjmlRule;
use EscolaLms\TemplatesEmail\Services\Contracts\MjmlServiceContract;
use EscolaLms\TemplatesEmail\Services\MjmlService;
use EscolaLms\TopicTypeProject\EscolaLmsTopicTypeProjectServiceProvider;
use EscolaLms\Video\EscolaLmsVideoServiceProvider;
use EscolaLms\Webinar\EscolaLmsWebinarServiceProvider;
use EscolaLms\Youtube\EscolaLmsYoutubeServiceProvider;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsTemplatesEmailServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'escola_templates_email';

    public $singletons = [
        MjmlServiceContract::class => MjmlService::class,
    ];

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);

        if (class_exists(\EscolaLms\Auth\EscolaLmsAuthServiceProvider::class)) {
            if (!$this->app->getProviders(EscolaLmsAuthServiceProvider::class)) {
                $this->app->register(EscolaLmsAuthServiceProvider::class);
            }
            $this->app->register(AuthTemplatesEventServiceProvider::class);
            $this->app->register(AuthTemplatesServiceProvider::class);
        }
        if (class_exists(\EscolaLms\Courses\EscolaLmsCourseServiceProvider::class)) {
            $this->app->register(CourseTemplatesServiceProvider::class);
        }
        if (class_exists(\EscolaLms\CsvUsers\EscolaLmsCsvUsersServiceProvider::class)) {
            $this->app->register(CsvUsersTemplatesServiceProvider::class);
        }
        if (
            class_exists(EscolaLmsTemplatesServiceProvider::class) &&
            !$this->app->getProviders(EscolaLmsTemplatesServiceProvider::class)
        ) {
            $this->app->register(EscolaLmsTemplatesServiceProvider::class);
        }
        if (class_exists(\EscolaLms\AssignWithoutAccount\EscolaLmsAssignWithoutAccountServiceProvider::class)) {
            $this->app->register(AssignWithoutAccountTemplatesEventServiceProvider::class);
        }
        if (class_exists(EscolaLmsConsultationsServiceProvider::class)) {
            $this->app->register(ConsultationTemplatesServiceProvider::class);
        }
        if (class_exists(EscolaLmsWebinarServiceProvider::class)) {
            $this->app->register(WebinarTemplatesServiceProvider::class);
        }
        if (class_exists(\EscolaLms\Cart\EscolaLmsCartServiceProvider::class)) {
            $this->app->register(CartTemplatesServiceProvider::class);
        }
        if (class_exists(EscolaLmsYoutubeServiceProvider::class)) {
            $this->app->register(YoutubeTemplatesServiceProvider::class);
        }

        if (class_exists(EscolaLmsCourseAccessServiceProvider::class)) {
            $this->app->register(CourseAccessTemplatesServiceProvider::class);
        }

        if (class_exists(EscolaLmsTasksServiceProvider::class)) {
            $this->app->register(TaskTemplatesServiceProvider::class);
        }

        if (class_exists(EscolaLmsConsultationAccessServiceProvider::class)) {
            $this->app->register(ConsultationAccessTemplatesServiceProvider::class);
        }

        if (class_exists(EscolaLmsTopicTypeProjectServiceProvider::class)) {
            $this->app->register(TopicTypeProjectTemplatesServiceProvider::class);
        }

        if (class_exists(EscolaLmsVideoServiceProvider::class)) {
            $this->app->register(VideoTemplatesServiceProvider::class);
        }

        $this->app->register(TemplateServiceProvider::class);

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'templates-email');
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/templates-email'),
        ]);
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }

        if (class_exists(\EscolaLms\Settings\Facades\AdministrableConfig::class)) {
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.mjml.api_url', ['required', 'string'], '');
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.mjml.use_api', ['required', 'bool'], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.mjml.api_id', ['required', 'string'], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.mjml.api_secret', ['required', 'string'], false);
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.mjml.default_template', ['required', 'string', new MjmlRule()], false);
        }

        CreatePasswordResetToken::setRunEventForgotPassword(
            function () {
                $templateRepository = app(TemplateRepositoryContract::class);
                return empty($templateRepository->findTemplateDefault(
                    ForgotPassword::class,
                    EmailChannel::class
                ));
            }
        );

        SendEmailVerificationNotification::setRunEventEmailVerification(
            function () {
                $templateRepository = app(TemplateRepositoryContract::class);
                return empty($templateRepository->findTemplateDefault(
                    AccountRegistered::class,
                    EmailChannel::class
                ));
            }
        );

        Setting::observe(SettingObserver::class);
    }

    public function bootForConsole()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
        ], self::CONFIG_KEY . '.config');
    }
}
