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
