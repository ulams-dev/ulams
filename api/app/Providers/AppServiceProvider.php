<?php

namespace App\Providers;

use App\Repositories\Contracts\SearchableEventRepositoryContract;
use App\Repositories\SearchableEventRepository;
use App\Services\ConsultationService;
use App\Services\Contracts\ConsultationServiceContract;
use App\Services\Contracts\SearchableEventServiceContract;
use App\Services\SearchableEventService;
use App\Support\LegacyMigrationNames;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public const SERVICES = [
        SearchableEventServiceContract::class => SearchableEventService::class,
        ConsultationServiceContract::class => ConsultationService::class,
    ];

    public const REPOSITORIES = [
        SearchableEventRepositoryContract::class => SearchableEventRepository::class,
    ];

    public $singletons = self::SERVICES + self::REPOSITORIES;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(\L5Swagger\L5SwaggerServiceProvider::class);

        // Passport 13 enables the device authorization grant and its oauth/device* routes by
        // default. Nothing uses them: the API issues personal access tokens only. Set before
        // any provider boots, because Passport registers its routes in boot().
        Passport::$deviceCodeGrantEnabled = false;

        // swagger-php 6 (l5-swagger 11) reads only PHP attributes by default. The API is
        // documented with `@OA\` docblock annotations (223 files), which need the DocBlock
        // factory (and doctrine/annotations). Set here because objects in config files cannot
        // be cached by `config:cache`.
        if (config('l5-swagger.defaults.scanOptions.analyser') === null) {
            config(['l5-swagger.defaults.scanOptions.analyser' => new \OpenApi\Analysers\ReflectionAnalyser([
                new \OpenApi\Analysers\DocBlockAnnotationFactory(),
                new \OpenApi\Analysers\AttributeAnnotationFactory(),
            ])]);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (strpos(config('app.url'), 'https') !== false) {
            \URL::forceScheme('https');
        }
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            if (str_starts_with((string) $event->command, 'migrate')) {
                LegacyMigrationNames::rename();
            }
        });
        if (DB::Connection() instanceof SQLiteConnection) {
            DB::connection()->getPdo()->sqliteCreateFunction('REGEXP', function ($pattern, $value) {
                mb_regex_encoding('UTF-8');
                return (false !== mb_ereg($pattern, $value)) ? 1 : 0;
            });
        }
    }
}
