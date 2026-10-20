<?php

namespace Ulams\Cart\Rules;

use Ulams\Cart\Facades\Shop;
use Ulams\Core\Support\MorphMap;
use Illuminate\Contracts\Validation\Rule;

class ProductableRegisteredRule implements Rule
{
    public function passes($attribute, $value): bool
    {
        $class = MorphMap::classOf($value);

        return is_string($class) && class_exists($class) && Shop::isProductableClassRegistered($class);
    }

    public function message(): string
    {
        return __('Class :input must represent registered Productable type.');
    }
}
