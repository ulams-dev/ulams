<?php

namespace App\Models;

use Ulams\Cart\Contracts\Productable;
use Ulams\Cart\Contracts\ProductableTrait;
use Ulams\Cart\Models\Product;
use Ulams\Core\Models\User;
use Ulams\Webinar\Events\WebinarUserAssigned;
use Ulams\Webinar\Events\WebinarUserUnassigned;
use Illuminate\Database\Eloquent\Collection;

class Webinar extends \Ulams\Webinar\Models\Webinar implements Productable
{
    use ProductableTrait;

    public function attachToUser(User $user, int $quantity = 1, ?Product $product = null): void
    {
        $this->users()->syncWithoutDetaching($user->getKey());
        event(new WebinarUserAssigned($user, $this));
    }

    public function detachFromUser(User $user, int $quantity = 1, ?Product $product = null): void
    {
        $this->users()->detach($user->getKey());
        event(new WebinarUserUnassigned($user, $this));
    }

    public function getProductableAuthors(): Collection
    {
        return $this->trainers;
    }
}
