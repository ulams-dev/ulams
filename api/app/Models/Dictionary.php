<?php

namespace App\Models;

use Ulams\Cart\Contracts\Productable;
use Ulams\Cart\Contracts\ProductableTrait;
use Ulams\Cart\Models\Product;
use Ulams\Core\Models\User;
use Ulams\Courses\Events\CourseAccessStarted;
use Ulams\Courses\Events\CourseAssigned;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Events\CourseUnassigned;
use Ulams\Dictionaries\Models\Dictionary as BaseDictionary;

class Dictionary extends BaseDictionary implements Productable
{
    use ProductableTrait;

    public function attachToUser(User $user, int $quantity = 1, ?Product $product = null): void
    {
        $productUser = $product?->users()->where('user_id', $user->getKey())->first()?->pivot;

        $this->users()->syncWithoutDetaching([$user->getKey() => ['end_date' => $productUser?->end_date]]);
    }

    public function detachFromUser(User $user, int $quantity = 1, ?Product $product = null): void
    {
        $this->users()->detach($user->getKey());
    }
}
