<?php

namespace Ulams\TemplatesEmail\Providers;

use Ulams\Cart\Events\OrderCreated;
use Ulams\Cart\Events\ProductAttached;
use Ulams\Templates\Facades\Template;
use Ulams\TemplatesEmail\Cart\OrderCreatedVariables;
use Ulams\TemplatesEmail\Cart\ProductAttachedVariables;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Illuminate\Support\ServiceProvider;

class CartTemplatesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(ProductAttached::class, EmailChannel::class, ProductAttachedVariables::class);
        Template::register(OrderCreated::class, EmailChannel::class, OrderCreatedVariables::class);
    }
}
