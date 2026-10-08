<?php

namespace Ulams\Recommender\Providers;

use Ulams\Recommender\Listeners\UpdateRecommenderModels;
use Ulams\Settings\Events\SettingPackageConfigUpdated;

class EventServiceProvider extends \Illuminate\Foundation\Support\Providers\EventServiceProvider
{
    protected $listen = [
        SettingPackageConfigUpdated::class => [
            UpdateRecommenderModels::class,
        ],
    ];
}

