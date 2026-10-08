<?php

namespace EscolaLms\Categories\Database\Seeders;

use Illuminate\Database\Seeder;
use EscolaLms\Categories\Models\Category;

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