<?php

namespace Ulams\AssignWithoutAccount\Database\Factories;

use Ulams\AssignWithoutAccount\Enums\UserSubmissionStatusEnum;
use Ulams\AssignWithoutAccount\Models\UserSubmission;
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
            'morphable_type' => 'Ulams\\' . $type . '\\Models\\' . $type,
            'morphable_id' => $this->faker->numberBetween(1),
            'status' => $this->faker->randomElement(UserSubmissionStatusEnum::getValues()),
        ];
    }
}
