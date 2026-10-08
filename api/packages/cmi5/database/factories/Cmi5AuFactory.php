<?php

namespace Ulams\Cmi5\Database\Factories;

use Ulams\Cmi5\Models\Cmi5;
use Ulams\Cmi5\Models\Cmi5Au;
use Illuminate\Database\Eloquent\Factories\Factory;

class Cmi5AuFactory extends Factory
{
    protected $model = Cmi5Au::class;

    public function definition()
    {
        return [
            'title' => $this->faker->words(3, true),
            'iri' => $this->faker->url(),
            'url' => $this->faker->url(),
            'cmi5_id' => Cmi5::factory()
        ];
    }
}
