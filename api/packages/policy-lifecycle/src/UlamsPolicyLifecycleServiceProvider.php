<?php

namespace Ulams\PolicyLifecycle;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Ulams\Compliance\UlamsComplianceServiceProvider;
use Ulams\CourseBuilder\Events\BuilderStageFinished;
use Ulams\CourseBuilder\UlamsCourseBuilderServiceProvider;
use Ulams\LivingCourse\UlamsLivingCourseServiceProvider;
use Ulams\PolicyLifecycle\Http\Controllers\DashboardController;
use Ulams\PolicyLifecycle\Http\Controllers\PolicyController;
use Ulams\PolicyLifecycle\Http\Controllers\VariantController;
use Ulams\PolicyLifecycle\Http\Controllers\VersionController;
use Ulams\PolicyLifecycle\Listeners\ContinueGeneration;
use Ulams\PolicyLifecycle\Events\CertificateRevokedNotice;
use Ulams\PolicyLifecycle\Events\PolicyChangeApproved;
use Ulams\PolicyLifecycle\Events\PolicyRetrainingAssigned;
use Ulams\PolicyLifecycle\Listeners\LinkCertificate;
use Ulams\PolicyLifecycle\Listeners\SendRevokedNotice;
use Ulams\PolicyLifecycle\Listeners\TrackRollouts;
use Ulams\PolicyLifecycle\Mail\CertificateRevokedNoticeVariables;
use Ulams\PolicyLifecycle\Mail\PolicyChangeApprovedVariables;
use Ulams\PolicyLifecycle\Mail\PolicyRetrainingAssignedVariables;
use Ulams\LivingCourse\Events\ProposalApplied;
use Ulams\Templates\Facades\Template;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesPdf\Events\CertificateRevoked;
use Ulams\TemplatesPdf\Events\PdfCreated;

/**
 * Policy lifecycle (Phase 6.1, ADR 0140, docs/plans/phase-6-remaining.md 4.3): a policy document turned into one cited
 * course per department, an owner dashboard and, with the next milestone, approved policy changes that update the
 * courses, supersede certificates and reassign training. It orchestrates course-builder, living-course, compliance and
 * templates-pdf through their services and events; none of them knows about policies.
 */
class UlamsPolicyLifecycleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ([UlamsCourseBuilderServiceProvider::class, UlamsLivingCourseServiceProvider::class, UlamsComplianceServiceProvider::class] as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Event::listen(BuilderStageFinished::class, ContinueGeneration::class);
        Event::listen(PdfCreated::class, LinkCertificate::class);
        Event::listen(ProposalApplied::class, [TrackRollouts::class, 'applied']);
        Event::listen(BuilderStageFinished::class, [TrackRollouts::class, 'stageFinished']);
        Event::listen(CertificateRevoked::class, SendRevokedNotice::class);
        $this->registerEmailTemplates();

        Route::group(['prefix' => 'api/admin/policies', 'middleware' => ['auth:api']], function () {
            Route::get('/', [PolicyController::class, 'index']);
            Route::post('/', [PolicyController::class, 'store']);
            Route::get('{policy}', [PolicyController::class, 'show']);
            Route::get('{policy}/versions', [PolicyController::class, 'versions']);
            Route::post('{policy}/versions', [VersionController::class, 'store']);
            Route::get('{policy}/versions/{number}', [VersionController::class, 'show'])->whereNumber('number');
            Route::get('{policy}/variants', [VariantController::class, 'index']);
            Route::post('{policy}/variants/generate', [VariantController::class, 'generate']);
            Route::post('{policy}/versions/{number}/approve', [VersionController::class, 'approve'])->whereNumber('number');
            Route::post('{policy}/versions/{number}/reject', [VersionController::class, 'reject'])->whereNumber('number');
            Route::get('{policy}/dashboard', [DashboardController::class, 'show']);
            Route::get('{policy}/dashboard/export', [DashboardController::class, 'export']);
        });
    }

    /** The e-mails of a policy change, editable like any other template; each also appears in-app and as a webhook. */
    private function registerEmailTemplates(): void
    {
        if (!class_exists(EmailChannel::class)) {
            return;
        }
        foreach ([
            PolicyRetrainingAssigned::class => PolicyRetrainingAssignedVariables::class,
            PolicyChangeApproved::class => PolicyChangeApprovedVariables::class,
            CertificateRevokedNotice::class => CertificateRevokedNoticeVariables::class,
        ] as $event => $variables) {
            Template::register($event, EmailChannel::class, $variables);
        }
    }
}
