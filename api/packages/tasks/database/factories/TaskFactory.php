<?php

namespace Ulams\Tasks\Database\Factories;

use Illuminate\Support\Carbon;
use Ulams\Core\Models\User;
use Ulams\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        $type = Str::ucfirst($this->faker->word) . $this->faker->numberBetween();

        return [
            'title' => $this->faker->word,
            'description' => $this->faker->text,
            'type' => $this->faker->word,
            'due_date' => Carbon::now(),
            'completed_at' => null,
            'user_id' => User::factory()->state(['email' => $this->faker->unique()->email]),
            'created_by_id' => User::factory()->state(['email' => $this->faker->unique()->email]),
            'related_type' => 'Ulams\\' . $type . '\\Models\\' . $type,
            'related_id' => $this->faker->numberBetween(1),
        ];
    }
}
