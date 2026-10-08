<?php

namespace Ulams\Bookmarks\Database\Factories;

use Ulams\Bookmarks\Models\Bookmark;
use Ulams\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BookmarkFactory extends Factory
{
    protected $model = Bookmark::class;

    public function definition(): array
    {
        $type = Str::ucfirst($this->faker->word) . $this->faker->numberBetween();

        return [
            'value' => $this->faker->word,
            'user_id' => User::factory()->state(['email' => $this->faker->unique()->email]),
            'bookmarkable_type' => 'Ulams\\' . $type . '\\Models\\' . $type,
            'bookmarkable_id' => $this->faker->numberBetween(1),
        ];
    }
}
