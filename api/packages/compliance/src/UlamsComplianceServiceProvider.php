<?php

namespace Ulams\Compliance;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Ulams\Compliance\Console\ProcessComplianceCommand;
use Ulams\Compliance\Events\CertificateExpired;
use Ulams\Compliance\Events\CertificateExpiringSoon;
use Ulams\Compliance\Events\TrainingAssigned;
use Ulams\Compliance\Events\TrainingDueSoon;
use Ulams\Compliance\Events\TrainingEscalated;
use Ulams\Compliance\Events\TrainingOverdue;
use Ulams\Compliance\Http\Controllers\AssignmentController;
use Ulams\Compliance\Http\Controllers\CourseSettingsController;
use Ulams\Compliance\Http\Controllers\ManagerController;
use Ulams\Compliance\Http\Controllers\PathController;
use Ulams\Compliance\Http\Controllers\RuleController;
use Ulams\Compliance\Http\Controllers\ReportController;
use Ulams\Compliance\Listeners\CertificateIssuedListener;
use Ulams\Compliance\Listeners\CourseFinishedListener;
use Ulams\Compliance\Events\PathCompleted;
use Ulams\Compliance\Events\PathEnrolled;
use Ulams\Compliance\Mail\CertificateExpiredVariables;
use Ulams\Compliance\Mail\CertificateExpiringSoonVariables;
use Ulams\Compliance\Mail\PathCompletedVariables;
use Ulams\Compliance\Mail\PathEnrolledVariables;
use Ulams\Compliance\Mail\TrainingAssignedVariables;
use Ulams\Compliance\Mail\TrainingDueSoonVariables;
use Ulams\Compliance\Mail\TrainingEscalatedVariables;
use Ulams\Compliance\Mail\TrainingOverdueVariables;
use Ulams\Compliance\Services\Contracts\ProgressResetter;
use Ulams\Compliance\Services\CourseSettings;
use Ulams\Compliance\Services\EloquentProgressResetter;
use Ulams\Compliance\Services\PolicyCertificateValidity;
use Ulams\Compliance\Services\RuleEngine;
use Ulams\CourseAccess\UlamsCourseAccessServiceProvider;
use Ulams\Core\Models\User;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\Templates\Facades\Template;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesPdf\Events\PdfCreated;
use Ulams\TemplatesPdf\Services\Contracts\CertificateValidityContract;
use Ulams\TemplatesPdf\UlamsTemplatesPdfServiceProvider;

/**
 * Certificate validity, mandatory training and compliance reporting (Phase 6.1,
 * docs/plans/phase-6-compliance.md). Builds on courses, course-access and templates-pdf through their
 * services and events; nothing in those packages knows about it.
 */
class UlamsComplianceServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'ulams_compliance';

    public $singletons = [
        CourseSettings::class => CourseSettings::class,
        ProgressResetter::class => EloquentProgressResetter::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);
        $this->app->register(UlamsCourseServiceProvider::class);
        $this->app->register(UlamsCourseAccessServiceProvider::class);
        $this->app->register(UlamsTemplatesPdfServiceProvider::class);

        // Per-course validity replaces the single templates-pdf setting, which stays the fallback.
        $fallback = fn () => $this->app->make(\Ulams\TemplatesPdf\Services\ConfigCertificateValidity::class);
        $this->app->singleton(CertificateValidityContract::class, fn ($app) => new PolicyCertificateValidity($app->make(CourseSettings::class), $fallback()));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Event::listen(CourseFinished::class, CourseFinishedListener::class);
        Event::listen(PdfCreated::class, CertificateIssuedListener::class);

        $this->registerEmailTemplates();
        $this->registerUserHook();
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([ProcessComplianceCommand::class]);
            $this->publishes([__DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php')], self::CONFIG_KEY . '.config');
        }
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('compliance:process')->dailyAt('06:00')->withoutOverlapping());
    }

    /**
     * Rules are evaluated whenever a user is saved (any User subclass: listening on the wildcard model event keeps
     * this independent of which user model the application configures). A failure here must never break saving a user.
     */
    private function registerUserHook(): void
    {
        Event::listen('eloquent.saved: *', function (string $event, array $payload) {
            $user = $payload[0] ?? null;
            if (!$user instanceof User) {
                return;
            }
            try {
                app(RuleEngine::class)->evaluateUser($user);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    private function registerEmailTemplates(): void
    {
        if (!class_exists(EmailChannel::class)) {
            return;
        }
        foreach ([
            TrainingAssigned::class => TrainingAssignedVariables::class,
            TrainingDueSoon::class => TrainingDueSoonVariables::class,
            TrainingOverdue::class => TrainingOverdueVariables::class,
            TrainingEscalated::class => TrainingEscalatedVariables::class,
            CertificateExpiringSoon::class => CertificateExpiringSoonVariables::class,
            CertificateExpired::class => CertificateExpiredVariables::class,
            PathEnrolled::class => PathEnrolledVariables::class,
            PathCompleted::class => PathCompletedVariables::class,
        ] as $event => $variables) {
            Template::register($event, EmailChannel::class, $variables);
        }
    }

    private function registerRoutes(): void
    {
        Route::group(['prefix' => 'api/admin/compliance', 'middleware' => ['auth:api'], 'where' => ['course' => '[0-9]+', 'id' => '[0-9]+', 'user' => '[0-9]+']], function () {
            Route::get('courses/{course}/settings', [CourseSettingsController::class, 'show']);
            Route::put('courses/{course}/settings', [CourseSettingsController::class, 'update']);
            Route::get('assignments', [AssignmentController::class, 'index']);
            Route::post('assignments', [AssignmentController::class, 'store']);
            Route::delete('assignments/{id}', [AssignmentController::class, 'destroy']);
            Route::get('managers', [ManagerController::class, 'index']);
            Route::put('managers/{user}', [ManagerController::class, 'update']);
            Route::get('paths', [PathController::class, 'index']);
            Route::post('paths', [PathController::class, 'store']);
            Route::get('paths/{id}', [PathController::class, 'show']);
            Route::put('paths/{id}', [PathController::class, 'update']);
            Route::delete('paths/{id}', [PathController::class, 'destroy']);
            Route::post('paths/{id}/enrollments', [PathController::class, 'enroll']);
            Route::get('rules', [RuleController::class, 'index']);
            Route::post('rules', [RuleController::class, 'store']);
            Route::put('rules/{id}', [RuleController::class, 'update']);
            Route::delete('rules/{id}', [RuleController::class, 'destroy']);
            Route::post('rules/{id}/evaluate', [RuleController::class, 'evaluate']);
            Route::get('report', [ReportController::class, 'index']);
            Route::get('report/export', [ReportController::class, 'export']);
        });
        Route::group(['prefix' => 'api/compliance', 'middleware' => ['auth:api']], function () {
            Route::get('my-assignments', [AssignmentController::class, 'mine']);
            Route::get('my-paths', [PathController::class, 'mine']);
            Route::get('team/assignments', [AssignmentController::class, 'team']);
        });
    }
}
