<?php

namespace EscolaLms\Tasks\Tests;

use Illuminate\Support\Carbon;
use EscolaLms\Core\Models\User;
use EscolaLms\Tasks\Models\Task;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;

trait TaskTesting
{
    use WithFaker;

    public function userCreationPayload(?array $data = []): array
    {
        $type = Str::ucfirst($this->faker->word) . $this->faker->numberBetween();

        $payload = [
            'title' => $this->faker->word,
            'description' => $this->faker->text,
            'type' => $this->faker->word,
            'due_date' => Carbon::now()->addDay(),
            'related_type' => 'EscolaLms\\' . $type . '\\Models\\' . $type,
            'related_id' => $this->faker->randomNumber(),
        ];

        return array_merge($payload, $data);
    }

    public function adminCreationPayload(?array $data = []): array
    {
        $type = Str::ucfirst($this->faker->word) . $this->faker->numberBetween();

        $payload = [
            'title' => $this->faker->word,
            'description' => $this->faker->text,
            'type' => $this->faker->word,
            'user_id' => User::factory()->create(['email' => $this->faker->email . Carbon::now()->getTimestamp()])->getKey(),
            'due_date' => Carbon::now()->addDay(),
            'related_type' => 'EscolaLms\\' . $type . '\\Models\\' . $type,
            'related_id' => $this->faker->randomNumber(),
        ];

        return array_merge($payload, $data);
    }

    public function creationTaskNotePayload(?array $data = [], ?User $user = null, ?User $createdBy = null): array
    {
        $user = $user ?? User::factory()->create(['email' => $this->faker->email . Carbon::now()->getTimestamp()]);
        $createdBy = $createdBy ?? User::factory()->create(['email' => $this->faker->email . Carbon::now()->getTimestamp()]);
        $task = Task::factory()->create(['user_id' => $user->getKey(), 'created_by_id' => $createdBy->getKey()]);

        $payload = [
            'note' => $this->faker->text,
            'task_id' => $task->getKey(),
        ];

        return array_merge($payload, $data);
    }

    public function updateTaskNotePayload(?array $data = []): array
    {
        $payload = [
            'note' => $this->faker->text,
            'task_id' => Task::factory()->create()->getKey(),
        ];

        return array_merge($payload, $data);
    }

    public function prepareUri(string $prefix, array $filters): string {
        return $prefix . '?' . http_build_query($filters);
    }

    public function assertDatabaseHasTask(array $data, ?array $additional = []): void
    {
        $assert = array_merge([
            'title' => $data['title'],
            'description' => $data['description'],
            'type' => $data['type'],
            'user_id' => $data['user_id'] ?? null,
            'created_by_id' => $data['created_by'] ?? null,
            'due_date' => $data['due_date'],
            'related_type' => $data['related_type'],
            'related_id' => $data['related_id'],
        ], $additional);

        $this->assertDatabaseHas('tasks', $assert);
    }
}
