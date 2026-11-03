<?php

namespace Tests\Integrations;

use Tests\TestCase;

/** `oauth/authorize` is public and the API has no consent screen: it must refuse bad requests with a 4xx, never a 500. */
class OauthAuthorizeTest extends TestCase
{
    public function testAuthorizeWithoutParametersAnswers400WithoutATrace(): void
    {
        $response = $this->getJson('/oauth/authorize');

        $response->assertStatus(400)->assertJsonPath('error', 'invalid_request');
        $this->assertStringNotContainsString('vendor/', (string) $response->getContent());
    }

    public function testAuthorizeWithAnUnknownClientAnswers401WithoutATrace(): void
    {
        $response = $this->getJson('/oauth/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => '11111111-1111-1111-1111-111111111111',
            'redirect_uri' => 'https://attacker.example/cb',
        ]));

        $response->assertStatus(401);
        $this->assertStringNotContainsString('vendor/', (string) $response->getContent());
    }
}
