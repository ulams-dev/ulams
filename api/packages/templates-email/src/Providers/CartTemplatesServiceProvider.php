<?php

namespace EscolaLms\TemplatesEmail\Providers;

use EscolaLms\Cart\Events\OrderCreated;
use EscolaLms\Cart\Events\ProductAttached;
use EscolaLms\Templates\Facades\Template;
use EscolaLms\TemplatesEmail\Cart\OrderCreatedVariables;
use EscolaLms\TemplatesEmail\Cart\ProductAttachedVariables;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
use Illuminate\Support\ServiceProvider;

class CartTemplatesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(ProductAttached::class, EmailChannel::class, ProductAttachedVariables::class);
        Template::register(OrderCreated::class, EmailChannel::class, OrderCreatedVariables::class);
    }
}
