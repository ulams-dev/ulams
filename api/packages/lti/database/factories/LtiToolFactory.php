<?php

namespace Ulams\Lti\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ulams\Lti\Models\LtiTool;

class LtiToolFactory extends Factory
{
    protected $model = LtiTool::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' tool',
            'oidc_login_url' => 'https://tool.example.test/lti/login',
            'launch_url' => 'https://tool.example.test/lti/launch',
            'deep_linking_url' => 'https://tool.example.test/lti/deep-link',
            'jwks_url' => 'https://tool.example.test/.well-known/jwks.json',
            'custom' => null,
            'share_name' => false,
            'share_email' => false,
            'enabled' => true,
        ];
    }
}
