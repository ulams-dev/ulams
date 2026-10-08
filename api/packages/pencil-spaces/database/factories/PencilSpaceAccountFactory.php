<?php

namespace Ulams\PencilSpaces\Database\Factories;

use Ulams\PencilSpaces\Models\PencilSpaceAccount;
use Ulams\PencilSpaces\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PencilSpaceAccountFactory extends Factory
{
    protected $model = PencilSpaceAccount::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'pencil_space_id' => $this->faker->uuid,
            'pencil_space_email' => $this->faker->email,
        ];
    }
}
