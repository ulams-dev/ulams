<?php

namespace EscolaLms\MailerLite\Providers;

use EscolaLms\Auth\Events\AccountBlocked;
use EscolaLms\Auth\Events\AccountConfirmed;
use EscolaLms\Cart\Events\AbandonedCartEvent;
use EscolaLms\Cart\Events\OrderCreated;
use EscolaLms\Cart\Events\ProductBought;
use EscolaLms\MailerLite\Enum\GroupNamesEnum;
use EscolaLms\MailerLite\Enum\PackageStatusEnum;
use EscolaLms\MailerLite\Services\Contracts\MailerLiteServiceContract;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (Config::get(SettingsServiceProvider::CONFIG_KEY . '.package_status', PackageStatusEnum::ENABLED) !== PackageStatusEnum::ENABLED) {
            return;
        }

        Event::listen(AccountConfirmed::class, function ($event) {
            /**
             * >>> event(new EscolaLms\Auth\Events\AccountConfirmed(App\Models\User::find(18)));
             */
            $newsletterKey = Config::get(SettingsServiceProvider::CONFIG_KEY . '.newsletter_field_key');
            $user = $event->user;
            if ($user->{$newsletterKey}) {
                app(MailerLiteServiceContract::class)->addSubscriberToGroup(
                    Config::get(SettingsServiceProvider::CONFIG_KEY . '.group_registered_group', GroupNamesEnum::REGISTERED_USERS),
                    $event->user
                );
            }
        });

        Event::listen(ProductBought::class, function ($event) {
            /**
             * >>> event(new EscolaLms\Cart\Events\ProductBought(EscolaLms\Cart\Models\Product::find(1), EscolaLms\Vouchers\Models\Order::find(1), EscolaLms\Core\Models\User::find(18)));
             */
            app(MailerLiteServiceContract::class)->addSubscriberToGroup(
                Config::get(SettingsServiceProvider::CONFIG_KEY . '.group_order_paid', GroupNamesEnum::ORDER_PAID),
                $event->getUser()
            );
        });

        Event::listen(AccountBlocked::class, function ($event) {
            /**
             * >>> event(new EscolaLms\Auth\Events\AccountBlocked(App\Models\User::find(18)));
             */
            app(MailerLiteServiceContract::class)->deleteSubscriber($event->getUser());
        });

        Event::listen(AbandonedCartEvent::class, function ($event) {
            /**
             * >>> event(new EscolaLms\Cart\Events\AbandonedCartEvent(EscolaLms\Cart\Models\Cart::find(1)));
             */
            app(MailerLiteServiceContract::class)->addSubscriberToGroup(
                Config::get(SettingsServiceProvider::CONFIG_KEY . '.group_left_cart', GroupNamesEnum::LEFT_CART),
                $event->getUser()
            );
        });

        Event::listen(OrderCreated::class, function ($event) {
            /**
             * >>> event(new EscolaLms\Cart\Events\OrderCreated(EscolaLms\Cart\Models\Order::find(1)));
             */
            app(MailerLiteServiceContract::class)->removeSubscriberFromGroup(
                Config::get(SettingsServiceProvider::CONFIG_KEY . '.group_left_cart', GroupNamesEnum::LEFT_CART),
                $event->getUser()
            );
        });
    }
}
