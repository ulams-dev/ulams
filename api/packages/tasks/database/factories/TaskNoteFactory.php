<?php

namespace EscolaLms\Tasks\Database\Factories;

use EscolaLms\Core\Models\User;
use EscolaLms\Tasks\Models\Task;
use EscolaLms\Tasks\Models\TaskNote;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskNoteFactory extends Factory
{
    protected $model = TaskNote::class;

    public function definition(): array
    {
        return [
            'note' => $this->faker->word,
            'user_id' => User::factory()->state(['email' => $this->faker->unique()->email]),
            'task_id' => Task::factory(),
        ];
    }
}
