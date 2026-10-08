<?php

namespace Ulams\Auth\Providers;

use Ulams\Auth\Events\AccountDeleted;
use Ulams\Auth\Events\AccountRegistered;
use Ulams\Auth\Events\ForgotPassword;
use Ulams\Auth\Listeners\CreatePasswordResetToken;
use Ulams\Auth\Listeners\EmailAnonymisation;
use Ulams\Auth\Listeners\RemoveUserSocialAccounts;
use Ulams\Auth\Listeners\SendEmailVerificationNotification;
use Ulams\Auth\Listeners\MaskUserData;

class EventServiceProvider extends \Illuminate\Foundation\Support\Providers\EventServiceProvider
{
    protected $listen = [
        AccountRegistered::class => [
            SendEmailVerificationNotification::class,
        ],
        ForgotPassword::class => [
            CreatePasswordResetToken::class,
        ],
        AccountDeleted::class => [
            EmailAnonymisation::class,
            RemoveUserSocialAccounts::class,
            MaskUserData::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }
}
