<?php

namespace Database\Factories\EscolaLms\Categories\Models;

use EscolaLms\Categories\Models\Category;
use EscolaLms\Categories\Services\Contracts\CategoryServiceContracts;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

class CategoryFactory extends Factory
{
    protected $model = Category::class;
    private CategoryServiceContracts $categoryService;

    public function definition(): array
    {
        $this->categoryService = app(CategoryServiceContracts::class);
        $name = $this->faker->word;

        return [
            'name' => $name,
            'description' => $this->faker->sentence,
            'slug' => $this->categoryService->slugify($name),
            'icon' => $this->makeIcon(),
            'is_active' => $this->faker->boolean,
            'parent_id' => $this->setParent(),
        ];
    }

    private function makeIcon(): string
    {
        return Storage::putFile('categories', __DIR__ . '/../multimedia/categories/' . rand(1, 5) . '.svg', 'public');
    }

    private function setParent(): ?int
    {
        return $this->faker->boolean ? optional(Category::select('id')->inRandomOrder()->first())->getKey() : null;
    }
}
