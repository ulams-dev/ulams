<?php

namespace Ulams\Identity\Tests\Support;

use Illuminate\Testing\TestResponse;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Services\Scim\Schemas;
use Ulams\Identity\Services\Scim\ScimTokenService;

/** Drives the SCIM server the way an identity provider's provisioning client does. */
trait RunsScim
{
    protected const SCIM = '/api/scim/v2';

    protected ?string $scimSecret = null;

    protected function scimProvider(array $attributes = []): IdentityProvider
    {
        return $this->provider($attributes + ['slug' => 'acme', 'trust_email' => false]);
    }

    /** A SCIM token for the provider; becomes the current one. */
    protected function scimToken(IdentityProvider $provider, string $name = 'Okta'): string
    {
        return $this->scimSecret = app(ScimTokenService::class)->issue($provider, $name, 90)->secret;
    }

    protected function scim(string $method, string $path, ?array $body = null, array $headers = [], ?string $token = null): TestResponse
    {
        // a request is a new connection: the guard must not remember the previous caller
        $this->app['auth']->forgetGuards();
        $server = ['HTTP_ACCEPT' => 'application/scim+json', 'CONTENT_TYPE' => 'application/scim+json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $token ??= $this->scimSecret;
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        return $this->call($method, self::SCIM . $path, [], [], [], $server, $body === null ? null : json_encode($body));
    }

    /** @return array<string,mixed> */
    protected function userBody(string $userName = 'ada@example.com', array $extra = []): array
    {
        return array_replace_recursive([
            'schemas' => [Schemas::USER],
            'userName' => $userName,
            'externalId' => 'ext-' . $userName,
            'name' => ['givenName' => 'Ada', 'familyName' => 'Lovelace'],
            'emails' => [['value' => $userName, 'type' => 'work', 'primary' => true]],
            'active' => true,
        ], $extra);
    }

    protected function createScimUser(string $userName = 'ada@example.com', array $extra = []): array
    {
        $response = $this->scim('POST', '/Users', $this->userBody($userName, $extra))->assertStatus(201);

        return $response->json();
    }

    /** @return array<string,mixed> */
    protected function groupBody(string $name = 'Nurses', array $members = [], array $extra = []): array
    {
        return array_replace([
            'schemas' => [Schemas::GROUP],
            'displayName' => $name,
            'externalId' => 'g-' . $name,
            'members' => array_map(fn ($id) => ['value' => (string) $id], $members),
        ], $extra);
    }

    protected function createScimGroup(string $name = 'Nurses', array $members = []): array
    {
        return $this->scim('POST', '/Groups', $this->groupBody($name, $members))->assertStatus(201)->json();
    }

    /** @param list<array<string,mixed>> $operations */
    protected function patchBody(array $operations): array
    {
        return ['schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'], 'Operations' => $operations];
    }
}
