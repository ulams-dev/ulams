<?php

namespace Ulams\Reports\Tests\Models;

use Ulams\Cart\Contracts\Productable;
use Ulams\Cart\Contracts\ProductableTrait;
use Ulams\Cart\Models\Product;
use Ulams\Core\Models\User;
use Ulams\Courses\Models\Course as BaseCourse;

class Course extends BaseCourse implements Productable
{
    use ProductableTrait;

    public function attachToUser(User $user, int $quantity = 1, ?Product $product = null): void
    {
        $this->users()->syncWithoutDetaching($user->getKey());
    }

    public static function getMorphClassStatic(): string
    {
        return parent::class;
    }
}
