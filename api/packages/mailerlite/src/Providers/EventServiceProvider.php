<?php

namespace Ulams\MailerLite\Providers;

use Ulams\Auth\Events\AccountBlocked;
use Ulams\Auth\Events\AccountConfirmed;
use Ulams\Cart\Events\AbandonedCartEvent;
use Ulams\Cart\Events\OrderCreated;
use Ulams\Cart\Events\ProductBought;
use Ulams\MailerLite\Enum\GroupNamesEnum;
use Ulams\MailerLite\Enum\PackageStatusEnum;
use Ulams\MailerLite\Services\Contracts\MailerLiteServiceContract;
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
             * >>> event(new Ulams\Auth\Events\AccountConfirmed(App\Models\User::find(18)));
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
             * >>> event(new Ulams\Cart\Events\ProductBought(Ulams\Cart\Models\Product::find(1), Ulams\Vouchers\Models\Order::find(1), Ulams\Core\Models\User::find(18)));
             */
            app(MailerLiteServiceContract::class)->addSubscriberToGroup(
                Config::get(SettingsServiceProvider::CONFIG_KEY . '.group_order_paid', GroupNamesEnum::ORDER_PAID),
                $event->getUser()
            );
        });

        Event::listen(AccountBlocked::class, function ($event) {
            /**
             * >>> event(new Ulams\Auth\Events\AccountBlocked(App\Models\User::find(18)));
             */
            app(MailerLiteServiceContract::class)->deleteSubscriber($event->getUser());
        });

        Event::listen(AbandonedCartEvent::class, function ($event) {
            /**
             * >>> event(new Ulams\Cart\Events\AbandonedCartEvent(Ulams\Cart\Models\Cart::find(1)));
             */
            app(MailerLiteServiceContract::class)->addSubscriberToGroup(
                Config::get(SettingsServiceProvider::CONFIG_KEY . '.group_left_cart', GroupNamesEnum::LEFT_CART),
                $event->getUser()
            );
        });

        Event::listen(OrderCreated::class, function ($event) {
            /**
             * >>> event(new Ulams\Cart\Events\OrderCreated(Ulams\Cart\Models\Order::find(1)));
             */
            app(MailerLiteServiceContract::class)->removeSubscriberFromGroup(
                Config::get(SettingsServiceProvider::CONFIG_KEY . '.group_left_cart', GroupNamesEnum::LEFT_CART),
                $event->getUser()
            );
        });
    }
}
