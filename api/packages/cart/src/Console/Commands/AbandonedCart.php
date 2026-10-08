<?php

namespace Ulams\Cart\Console\Commands;

use Carbon\Carbon;
use Ulams\Cart\Events\AbandonedCartEvent;
use Ulams\Cart\Models\Cart;
use Ulams\Cart\Services\Contracts\ShopServiceContract;
use Illuminate\Console\Command;

class AbandonedCart extends Command
{
    protected $signature = 'cart:abandoned-event';

    protected $description = 'Find all abandoned cart in 24-48h and run event';

    private ShopServiceContract $shopService;

    public function __construct(ShopServiceContract $shopService)
    {
        parent::__construct();
        $this->shopService = $shopService;
    }

    public function handle(): void
    {
        $abandonedCarts = $this->shopService->getAbandonedCarts(Carbon::now()->subHours(24), Carbon::now());
        /** @var Cart $abandonedCart */
        foreach ($abandonedCarts as $abandonedCart) {
            event(new AbandonedCartEvent($abandonedCart));
        }
    }
}
