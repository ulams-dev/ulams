<?php

namespace Ulams\CourseVariants;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Ulams\Courses\Http\Resources\CourseListResource;
use Ulams\Courses\Http\Resources\CourseSimpleResource;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Support\CourseVisibility;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\CourseVariants\Console\AssignByRulesCommand;
use Ulams\CourseVariants\Contracts\ConditionMatcher;
use Ulams\CourseVariants\Contracts\FitSignals;
use Ulams\CourseVariants\Contracts\PlacementSource;
use Ulams\CourseVariants\Contracts\TopicCorrespondence;
use Ulams\CourseVariants\Http\Controllers\GroupAdminController;
use Ulams\CourseVariants\Http\Controllers\PlacementController;
use Ulams\CourseVariants\Http\Controllers\RuleAdminController;
use Ulams\CourseVariants\Http\Controllers\VariantController;
use Ulams\CourseVariants\Listeners\RefreshFamilyAccess;
use Ulams\CourseVariants\Models\VariantGroupMember;
use Ulams\CourseVariants\Services\Contracts\CourseVariantServiceContract;
use Ulams\CourseVariants\Services\CourseVariantService;
use Ulams\CourseVariants\Services\FamilyDirectory;
use Ulams\CourseVariants\Support\ComplianceConditionMatcher;
use Ulams\CourseVariants\Support\NoFitSignals;
use Ulams\CourseVariants\Support\NoPlacementSource;
use Ulams\CourseVariants\Support\NoTopicCorrespondence;
use Ulams\Entitlements\Events\AccessProjected;
use Ulams\Entitlements\UlamsEntitlementsServiceProvider;

/**
 * Course variants, LMS side (ADR 0192): families of courses that are variants of one course by level and role, and the
 * routing of learners between them (CV-5): the learner's choice, an admin's or a rule's assignment, the optional
 * placement check, hints, one entitlement for the family and progress carried over. The Course Builder registers the
 * courses it applies and supplies the topic correspondence and the placement questions; Learner Insights supplies the
 * hint rules. This package does not depend on any of them.
 */
class UlamsCourseVariantsServiceProvider extends ServiceProvider
{
    public $singletons = [
        CourseVariantServiceContract::class => CourseVariantService::class,
        FamilyDirectory::class => FamilyDirectory::class,
    ];

    public function register(): void
    {
        $this->app->register(UlamsCourseServiceProvider::class);
        $this->app->register(UlamsEntitlementsServiceProvider::class);

        // defaults for what other packages supply: bindIf leaves a binding that another provider made first, and the
        // other providers bind after this one has registered, so either order ends with theirs
        $this->app->bindIf(TopicCorrespondence::class, NoTopicCorrespondence::class);
        $this->app->bindIf(PlacementSource::class, NoPlacementSource::class);
        $this->app->bindIf(FitSignals::class, NoFitSignals::class);
        $this->app->bindIf(ConditionMatcher::class, ComplianceConditionMatcher::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->registerRoutes();

        Event::listen(AccessProjected::class, RefreshFamilyAccess::class);

        // the variants are not in the catalogue (findable is off) but their page and programme are open (ADR 0192)
        CourseVisibility::viewableWhen(fn (Course $course) => VariantGroupMember::query()->where('course_id', $course->getKey())->whereNull('retired_at')->exists());
        // the default course's card says which variants exist ("Available for: Beginner, Expert"); a variant's page names its family
        $family = fn ($resource) => ['variant_family' => $this->app->make(FamilyDirectory::class)->forCourse((int) ($resource->resource->id ?? $resource->resource->getKey()))];
        CourseListResource::extend($family);
        CourseSimpleResource::extend($family);

        if ($this->app->runningInConsole()) {
            $this->commands([AssignByRulesCommand::class]);
        }
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('course-variants:assign-by-rules')->dailyAt('06:30')->withoutOverlapping());
    }

    private function registerRoutes(): void
    {
        $where = ['course' => '[0-9]+', 'hint' => '[0-9]+', 'rule' => '[0-9]+', 'group' => '[0-9A-Za-z]{26}'];
        // public, like the course page: labels and descriptions of the variants (ADR 0190 lists it)
        Route::get('api/courses/{course}/variants', [VariantController::class, 'variants'])->where($where);
        Route::group(['prefix' => 'api/courses', 'middleware' => ['auth:api'], 'where' => $where], function () {
            Route::get('{course}/variant', [VariantController::class, 'show']);
            Route::put('{course}/variant', [VariantController::class, 'choose']);
            Route::post('{course}/variant/hints/{hint}/dismiss', [VariantController::class, 'dismissHint']);
            Route::get('{course}/placement', [PlacementController::class, 'show']);
            Route::post('{course}/placement/attempts', [PlacementController::class, 'attempt']);
        });
        Route::group(['prefix' => 'api/admin/course-variant-groups', 'middleware' => ['auth:api'], 'where' => $where], function () {
            Route::get('/', [GroupAdminController::class, 'index']);
            Route::get('{group}', [GroupAdminController::class, 'show']);
            Route::put('{group}', [GroupAdminController::class, 'update']);
            Route::get('{group}/assignments', [GroupAdminController::class, 'assignments']);
            Route::put('{group}/assignments', [GroupAdminController::class, 'assign']);
            Route::get('{group}/placement', [GroupAdminController::class, 'placementShow']);
            Route::post('{group}/placement/rebuild', [GroupAdminController::class, 'placementRebuild']);
            Route::get('{group}/rules', [RuleAdminController::class, 'index']);
            Route::post('{group}/rules', [RuleAdminController::class, 'store']);
            Route::put('{group}/rules/{rule}', [RuleAdminController::class, 'update']);
            Route::delete('{group}/rules/{rule}', [RuleAdminController::class, 'destroy']);
            Route::post('{group}/rules/{rule}/evaluate', [RuleAdminController::class, 'evaluate']);
        });
    }
}
