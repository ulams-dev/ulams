<?php

namespace EscolaLms\AssignWithoutAccount\Database\Factories;

use EscolaLms\AssignWithoutAccount\Enums\UserSubmissionStatusEnum;
use EscolaLms\AssignWithoutAccount\Models\UserSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserSubmissionFactory extends Factory
{
    protected $model = UserSubmission::class;

    public function definition()
    {
        $type = Str::ucfirst($this->faker->word) . $this->faker->numberBetween();

        return [
            'email' => $this->faker->email,
            'morphable_type' => 'EscolaLms\\' . $type . '\\Models\\' . $type,
            'morphable_id' => $this->faker->numberBetween(1),
            'status' => $this->faker->randomElement(UserSubmissionStatusEnum::getValues()),
        ];
    }
}
