<?php

namespace Ulams\Images\Providers;

use Ulams\Images\Events\File;
use Ulams\Images\Events\FileDeleted;
use Ulams\Images\Events\FileStored;
use Ulams\Images\Services\Contracts\ImagesServiceContract;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProviders extends ServiceProvider
{
    public function boot()
    {
        Event::listen([FileDeleted::class, FileStored::class], function (File $event) {
             app(ImagesServiceContract::class)->clearImageCacheByDirectory($event->getPath());
        });
    }
}
