<?php

namespace EscolaLms\Vouchers\Database\Factories;

use Database\Factories\EscolaLms\Categories\Models\CategoryFactory as BaseCategoryFactory;
use EscolaLms\Vouchers\Models\Category;

class CategoryFactory extends BaseCategoryFactory
{
    protected $model = Category::class;
}
