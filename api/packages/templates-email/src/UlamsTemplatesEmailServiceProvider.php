<?php

namespace Ulams\TemplatesEmail;

use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Auth\Events\AccountRegistered;
use Ulams\Auth\Events\ForgotPassword;
use Ulams\Auth\Listeners\CreatePasswordResetToken;
use Ulams\Auth\Listeners\SendEmailVerificationNotification;
use Ulams\ConsultationAccess\UlamsConsultationAccessServiceProvider;
use Ulams\Consultations\UlamsConsultationsServiceProvider;
use Ulams\CourseAccess\UlamsCourseAccessServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;
use Ulams\Settings\Models\Setting;
use Ulams\Tasks\UlamsTasksServiceProvider;
use Ulams\Templates\UlamsTemplatesServiceProvider;
use Ulams\Templates\Repository\Contracts\TemplateRepositoryContract;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\Observers\SettingObserver;
use Ulams\TemplatesEmail\Providers\AssignWithoutAccountTemplatesEventServiceProvider;
use Ulams\TemplatesEmail\Providers\AuthTemplatesEventServiceProvider;
use Ulams\TemplatesEmail\Providers\AuthTemplatesServiceProvider;
use Ulams\TemplatesEmail\Providers\ConsultationAccessTemplatesServiceProvider;
use Ulams\TemplatesEmail\Providers\ConsultationTemplatesServiceProvider;
use Ulams\TemplatesEmail\Providers\CartTemplatesServiceProvider;
use Ulams\TemplatesEmail\Providers\CourseAccessTemplatesServiceProvider;
use Ulams\TemplatesEmail\Providers\CourseTemplatesServiceProvider;
use Ulams\TemplatesEmail\Providers\CsvUsersTemplatesServiceProvider;
use Ulams\TemplatesEmail\Providers\TaskTemplatesServiceProvider;
use Ulams\TemplatesEmail\Providers\TemplateServiceProvider;
use Ulams\TemplatesEmail\Providers\TopicTypeProjectTemplatesServiceProvider;
use Ulams\TemplatesEmail\Providers\VideoTemplatesServiceProvider;
use Ulams\TemplatesEmail\Providers\WebinarTemplatesServiceProvider;
use Ulams\TemplatesEmail\Providers\YoutubeTemplatesServiceProvider;
use Ulams\TemplatesEmail\Rules\MjmlRule;
use Ulams\TemplatesEmail\Services\Contracts\MjmlServiceContract;
use Ulams\TemplatesEmail\Services\MjmlService;
use Ulams\TopicTypeProject\UlamsTopicTypeProjectServiceProvider;
use Ulams\Video\UlamsVideoServiceProvider;
use Ulams\Webinar\UlamsWebinarServiceProvider;
use Ulams\Youtube\UlamsYoutubeServiceProvider;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsTemplatesEmailServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'ulams_templates_email';

    public $singletons = [
        MjmlServiceContract::class => MjmlService::class,
    ];

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);

        if (class_exists(\Ulams\Auth\UlamsAuthServiceProvider::class)) {
            if (!$this->app->getProviders(UlamsAuthServiceProvider::class)) {
                $this->app->register(UlamsAuthServiceProvider::class);
            }
            $this->app->register(AuthTemplatesEventServiceProvider::class);
            $this->app->register(AuthTemplatesServiceProvider::class);
        }
        if (class_exists(\Ulams\Courses\UlamsCourseServiceProvider::class)) {
            $this->app->register(CourseTemplatesServiceProvider::class);
        }
        if (class_exists(\Ulams\LivingCourse\UlamsLivingCourseServiceProvider::class)) {
            $this->app->register(\Ulams\TemplatesEmail\Providers\LivingCourseTemplatesServiceProvider::class);
        }
        if (class_exists(\Ulams\CsvUsers\UlamsCsvUsersServiceProvider::class)) {
            $this->app->register(CsvUsersTemplatesServiceProvider::class);
        }
        if (
            class_exists(UlamsTemplatesServiceProvider::class) &&
            !$this->app->getProviders(UlamsTemplatesServiceProvider::class)
        ) {
            $this->app->register(UlamsTemplatesServiceProvider::class);
        }
        if (class_exists(\Ulams\AssignWithoutAccount\UlamsAssignWithoutAccountServiceProvider::class)) {
            $this->app->register(AssignWithoutAccountTemplatesEventServiceProvider::class);
        }
        if (class_exists(UlamsConsultationsServiceProvider::class)) {
            $this->app->register(ConsultationTemplatesServiceProvider::class);
        }
        if (class_exists(UlamsWebinarServiceProvider::class)) {
            $this->app->register(WebinarTemplatesServiceProvider::class);
        }
        if (class_exists(\Ulams\Cart\UlamsCartServiceProvider::class)) {
            $this->app->register(CartTemplatesServiceProvider::class);
        }
        if (class_exists(UlamsYoutubeServiceProvider::class)) {
            $this->app->register(YoutubeTemplatesServiceProvider::class);
        }

        if (class_exists(UlamsCourseAccessServiceProvider::class)) {
            $this->app->register(CourseAccessTemplatesServiceProvider::class);
        }

        if (class_exists(UlamsTasksServiceProvider::class)) {
            $this->app->register(TaskTemplatesServiceProvider::class);
        }

        if (class_exists(UlamsConsultationAccessServiceProvider::class)) {
            $this->app->register(ConsultationAccessTemplatesServiceProvider::class);
        }

        if (class_exists(UlamsTopicTypeProjectServiceProvider::class)) {
            $this->app->register(TopicTypeProjectTemplatesServiceProvider::class);
        }

        if (class_exists(UlamsVideoServiceProvider::class)) {
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

        if (class_exists(\Ulams\Settings\Facades\AdministrableConfig::class)) {
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
