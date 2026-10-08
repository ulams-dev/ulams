<?php

namespace Ulams\Categories\Database\Seeders;

use Illuminate\Database\Seeder;
use Ulams\Categories\Models\Category;

class CategoriesSeeder extends Seeder
{
    public function run()
    {
        $categories = Category::factory(10)->create();
        foreach ($categories as $category) {
            $category->children()->save(Category::factory()->create());
        }
    }
}