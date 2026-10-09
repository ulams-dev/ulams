<?php

namespace Ulams\Lrs\Tests\Traits;

use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Ulams\Lrs\Database\Seeders\LrsSeeder;
use Ulams\Lrs\Enums\XApiEnum;
use Ulams\Lrs\Models\Access;

trait XapiTesting
{
    protected Access $access;
    protected string $token;
    protected string $endpoint;

    protected function setUpXapi(): void
    {
        Passport::personalAccessTokensExpireIn(now()->addDay());
        $this->seed(LrsSeeder::class);
        $this->access = Access::query()->latest('id')->firstOrFail();
        $this->endpoint = '/trax/api/' . $this->access->uuid . '/xapi/std';

        $user = config('auth.providers.users.model')::factory()->create();
        $this->token = $user->createToken('xAPI test')->accessToken;
    }

    protected function xapiHeaders(?string $authorization = null): array
    {
        return [
            'Authorization' => $authorization ?? 'Basic ' . $this->token,
            'X-Experience-API-Version' => XApiEnum::API_VERSION,
        ];
    }

    protected function xapi(string $method, string $path, mixed $body = null, array $headers = [], ?string $endpoint = null): TestResponse
    {
        $headers = array_merge($this->xapiHeaders(), $headers);
        $content = $body === null ? '' : (is_string($body) ? $body : json_encode($body));
        $headers += ['Content-Type' => 'application/json'];

        $server = [];
        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', $name));
            $server[in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $key : 'HTTP_' . $key] = $value;
        }

        return $this->call($method, ($endpoint ?? $this->endpoint) . $path, [], [], [], $server, $content);
    }

    protected function statement(array $overrides = []): array
    {
        return array_merge([
            'actor' => ['objectType' => 'Agent', 'mbox' => 'mailto:learner@example.com'],
            'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/initialized', 'display' => ['en-US' => 'initialized']],
            'object' => ['id' => 'https://example.com/activities/course-1'],
        ], $overrides);
    }
}
