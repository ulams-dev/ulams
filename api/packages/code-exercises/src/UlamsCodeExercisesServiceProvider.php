<?php

namespace Ulams\CodeExercises;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Ulams\CodeExercises\Http\Resources\CodeExerciseTopicExportResource;
use Ulams\CodeExercises\Http\Resources\CodeExerciseTopicResource;
use Ulams\CodeExercises\Models\CodeExerciseTopic;
use Ulams\CodeExercises\Services\AttemptService;
use Ulams\CodeExercises\Services\ExerciseFilesValidator;
use Ulams\Core\Support\MorphMap;
use Ulams\Courses\Facades\Topic;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\Settings\Facades\AdministrableConfig;
use Ulams\Settings\Services\Contracts\AdministrableConfigServiceContract;
use Ulams\TopicTypes\UlamsTopicTypesServiceProvider;
use Ulams\Uploads\UlamsUploadsServiceProvider;

/**
 * Code exercises topic type (ADR 0170): a topic holds starter files and visible tests; the learner edits
 * and runs them in the browser sandbox on the content origin, and the lesson page reports the results
 * here as practice attempts. Topics are created and changed through the topic API.
 */
class UlamsCodeExercisesServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'ulams_code_exercises';

    public $singletons = [
        AttemptService::class => AttemptService::class,
        ExerciseFilesValidator::class => ExerciseFilesValidator::class,
    ];

    public function register(): void
    {
        MorphMap::register([
            'topic.code_exercise' => \Ulams\CodeExercises\Models\CodeExerciseTopic::class,
        ]);

        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);
        $this->app->register(UlamsUploadsServiceProvider::class);
        $this->app->register(UlamsTopicTypesServiceProvider::class);
        $this->app->register(UlamsCourseServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // the runner's static files are served by the content-origin proxy route (packages/uploads) from this disk
        $disk = (string) config(self::CONFIG_KEY . '.runtime_disk');
        if (config('filesystems.disks.' . $disk) === null) {
            config(['filesystems.disks.' . $disk => [
                'driver' => 'local',
                'root' => config(self::CONFIG_KEY . '.runtime_path') ?: __DIR__ . '/../resources',
                'throw' => false,
            ]]);
        }

        // Code is whitespace-sensitive and a file may be empty: the exercise's files reach the model exactly as sent
        TrimStrings::except(['files.*.content', 'visible_tests.*.content']);
        ConvertEmptyStringsToNull::skipWhen(fn (Request $request) => in_array($request->input('topicable_type'), [CodeExerciseTopic::class, 'topic.code_exercise'], true));

        RateLimiter::for('code-exercise-attempts', fn (Request $request) => Limit::perMinute((int) config(self::CONFIG_KEY . '.limits.attempts_per_minute'))->by('code-attempts:' . ($request->user()?->getKey() ?? $request->ip())));

        Topic::registerContentClass(CodeExerciseTopic::class);
        Topic::registerResourceClasses(CodeExerciseTopic::class, [
            'client' => CodeExerciseTopicResource::class,
            'admin' => CodeExerciseTopicResource::class,
            'export' => CodeExerciseTopicExportResource::class,
        ]);

        // `GET /api/config` → ulams_code_exercises: {enabled}; the admin hides the type when it is off
        if ($this->app->bound(AdministrableConfigServiceContract::class)) {
            AdministrableConfig::registerConfig(self::CONFIG_KEY . '.enabled', ['required', 'boolean'], true);
        }
    }
}
