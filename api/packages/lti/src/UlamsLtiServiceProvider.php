<?php

namespace Ulams\Lti;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Ulams\CourseAccess\UlamsCourseAccessServiceProvider;
use Ulams\Courses\Events\TopicFinished;
use Ulams\Courses\Facades\Topic;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\Lti\Console\RotateKeysCommand;
use Ulams\Lti\Http\Middleware\IsolateLtiBearer;
use Ulams\Lti\Http\Resources\TopicType\LtiLinkResource;
use Ulams\Lti\Listeners\QueueGradePassback;
use Ulams\Lti\Models\LtiLink;
use Ulams\Lti\Platform\AgsService;
use Ulams\Lti\Platform\DeepLinkingService;
use Ulams\Lti\Platform\PlatformLaunchService;
use Ulams\Lti\Platform\RoleMapper;
use Ulams\Lti\Platform\ToolJwtVerifier;
use Ulams\Lti\Services\KeyService;
use Ulams\Lti\Services\NonceStore;
use Ulams\Lti\Support\HintSigner;
use Ulams\Lti\Support\JwksFetcher;
use Ulams\Lti\Tool\ServerSideState;
use Ulams\Lti\Tool\ToolCache;
use Ulams\Lti\Tool\ToolDatabase;
use Ulams\Lti\Tool\ToolLaunchService;
use Ulams\TopicTypes\UlamsTopicTypesServiceProvider;

/**
 * LTI 1.3 platform and tool (ADR 0012).
 */
class UlamsLtiServiceProvider extends ServiceProvider
{
    public const CONFIG_KEY = 'ulams_lti';

    public $singletons = [
        KeyService::class => KeyService::class,
        NonceStore::class => NonceStore::class,
        HintSigner::class => HintSigner::class,
        JwksFetcher::class => JwksFetcher::class,
        RoleMapper::class => RoleMapper::class,
        ToolJwtVerifier::class => ToolJwtVerifier::class,
        PlatformLaunchService::class => PlatformLaunchService::class,
        DeepLinkingService::class => DeepLinkingService::class,
        AgsService::class => AgsService::class,
        ToolDatabase::class => ToolDatabase::class,
        ToolCache::class => ToolCache::class,
        ServerSideState::class => ServerSideState::class,
        ToolLaunchService::class => ToolLaunchService::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);
        $this->app->register(UlamsTopicTypesServiceProvider::class);
        $this->app->register(UlamsCourseServiceProvider::class);
        $this->app->register(UlamsCourseAccessServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'lti');

        $kernel = $this->app->make(HttpKernel::class);
        if (method_exists($kernel, 'prependMiddleware')) {
            $kernel->prependMiddleware(IsolateLtiBearer::class);
        }

        Topic::registerContentClass(LtiLink::class);
        Topic::registerResourceClasses(LtiLink::class, [
            'client' => LtiLinkResource::class,
            'admin' => LtiLinkResource::class,
            'export' => LtiLinkResource::class,
        ]);

        Event::listen(TopicFinished::class, QueueGradePassback::class);

        if ($this->app->runningInConsole()) {
            $this->commands([RotateKeysCommand::class]);
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                $schedule->command('ulams:lti:rotate-keys')->monthlyOn(1, '03:00');
            });
        }
    }
}
