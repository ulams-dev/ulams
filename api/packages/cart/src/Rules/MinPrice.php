<?php

namespace Ulams\Cart\Rules;

use Ulams\Cart\UlamsCartServiceProvider;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Config;

class MinPrice implements Rule
{
    protected int $min;
    public function __construct()
    {
        $this->min = Config::get(UlamsCartServiceProvider::CONFIG_KEY . '.min_product_price', 0);
    }

    public function passes($attribute, $value): bool
    {
        return $value >= $this->min;
    }

    public function message(): string
    {
        return __('Field :attribute must be greater than or equal to ' . ($this->min / 100) . '.');
    }
}
