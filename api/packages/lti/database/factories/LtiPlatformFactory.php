<?php

namespace Ulams\Lti\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Ulams\Lti\Models\LtiPlatform;

class LtiPlatformFactory extends Factory
{
    protected $model = LtiPlatform::class;

    public function definition(): array
    {
        return [
            'name' => 'Moodle ' . $this->faker->city(),
            'issuer' => 'https://moodle.example.test',
            'client_id' => (string) Str::uuid(),
            'deployment_ids' => ['1'],
            'auth_login_url' => 'https://moodle.example.test/mod/lti/auth.php',
            'auth_token_url' => 'https://moodle.example.test/mod/lti/token.php',
            'jwks_url' => 'https://moodle.example.test/mod/lti/certs.php',
            'enabled' => true,
        ];
    }
}
