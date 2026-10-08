<?php

namespace Ulams\Video;

use Ulams\Courses\Facades\Topic;
use Ulams\TopicTypes\Events\TopicTypeChanged;
use Ulams\TopicTypes\Http\Resources\TopicType\Admin\VideoResource as VideoAdminResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Client\VideoResource as VideoClientResource;
use Ulams\Video\Enums\VideoProcessState;
use Ulams\Video\Jobs\ProcessVideo;
use Ulams\Video\Models\Video;
use Ulams\Video\Providers\ScheduleServiceProvider;
use Ulams\Video\Providers\SettingsServiceProvider;
use Ulams\Video\Repositories\Contracts\VideoRepositoryContract;
use Ulams\Video\Repositories\VideoRepository;
use Ulams\Video\Strategies\VideoStrategyResourceContext;
use function Illuminate\Events\queueable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class UlamsVideoServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'ulams_video';

    public $singletons = [
        VideoRepositoryContract::class => VideoRepository::class,
    ];

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);

        $this->app->register(AuthServiceProvider::class);
        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(ScheduleServiceProvider::class);

        Topic::registerContentClass(Video::class);
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'video');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }

        if (config('ulams_video.enable')) {
            Event::listen(queueable(function (TopicTypeChanged $event) {
                if (($event->getTopicContent() instanceof \Ulams\TopicTypes\Models\TopicContent\Video)) {
                    $video = Video::findOrFail($event->getTopicContent()->getKey());
                    $topic = $video->topic;

                    if (isset($topic)) {
                        $arr = is_array($topic->json) ? $topic->json : [];
                        $topic->json = array_merge($arr, ['ffmpeg' => [
                            'state' => VideoProcessState::QUEUE
                        ]]);
                        $topic->active = false;
                        $topic->save();
                        ProcessVideo::dispatch($video, $event->getUser());
                    }
                }
            }));

            $this->extendResources();
        }
    }
    public function bootForConsole()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->publishes([
            __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
        ], self::CONFIG_KEY . '.config');
    }

    private function extendResources(): void
    {
        VideoClientResource::extend(function ($thisObj) {
            return (new VideoStrategyResourceContext())->getStrategy()->clientResource($thisObj);
        });
        VideoAdminResource::extend(function ($thisObj) {
            return (new VideoStrategyResourceContext())->getStrategy()->adminResource($thisObj);
        });
    }
}
