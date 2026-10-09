<?php

namespace Tests\Integrations;

use Tests\TestCase;

class RemovedDebugRoutesTest extends TestCase
{
    /**
     * The debug endpoint let unauthenticated callers broadcast arbitrary messages to any (also private) channel.
     */
    public function testWebsocketTestEndpointIsGone(): void
    {
        $this->postJson('/api/test-websockets', [
            'channel' => 'private-user.1',
            'message' => 'test',
            'private' => true,
        ])->assertNotFound();
    }
}
