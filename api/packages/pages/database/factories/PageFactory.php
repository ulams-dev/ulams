<?php
namespace Database\Factories\EscolaLms\Pages\Models;

use Database\Factories\EscolaLms\Core\Models\UserFactory;
use DavidBadura\FakerMarkdownGenerator\FakerProvider;
use EscolaLms\Pages\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PageFactory extends Factory
{
    protected $model = Page::class;

    public function __construct($count = null, ?Collection $states = null, ?Collection $has = null, ?Collection $for = null, ?Collection $afterMaking = null, ?Collection $afterCreating = null, $connection = null)
    {
        parent::__construct($count, $states, $has, $for, $afterMaking, $afterCreating, $connection);
        $this->faker->addProvider(new FakerProvider($this->faker));
    }

    public function definition()
    {
        $title = $this->faker->catchPhrase;
        return [
            'slug' => Str::slug($title, '-'),
            'title' => $title,
            'author_id' => UserFactory::new(),
            'content' => $this->faker->markdown(),
            'active' => true
        ];
    }
}
