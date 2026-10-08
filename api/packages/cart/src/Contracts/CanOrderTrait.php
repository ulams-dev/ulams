<?php

namespace Ulams\Cart\Contracts;

use Ulams\Cart\Models\Cart;
use Ulams\Cart\Models\Order;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Models\ProductUser;
use Ulams\Core\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait CanOrderTrait
{
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'products_users')->using(ProductUser::class)->withPivot('quantity');
    }

    public function orders(): HasMany
    {
        /** @var User $this */
        return $this->hasMany(Order::class, 'user_id');
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class, 'user_id');
    }
}
