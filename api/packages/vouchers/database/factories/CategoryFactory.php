<?php

namespace Ulams\Vouchers\Database\Factories;

use Database\Factories\Ulams\Categories\Models\CategoryFactory as BaseCategoryFactory;
use Ulams\Vouchers\Models\Category;

class CategoryFactory extends BaseCategoryFactory
{
    protected $model = Category::class;
}
