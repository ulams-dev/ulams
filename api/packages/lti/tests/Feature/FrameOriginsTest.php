<?php

namespace Ulams\Lti\Tests\Feature;

use Ulams\Lti\Models\LtiTool;
use Ulams\Lti\Tests\TestCase;

/** `GET /api/lti/frame-origins`: what the front's Content Security Policy allows in frame-src (ADR 0044). */
class FrameOriginsTest extends TestCase
{
    public function testItListsTheOriginsOfEnabledToolsOnly(): void
    {
        LtiTool::factory()->create([
            'oidc_login_url' => 'https://www.geogebra.org/lti/login?x=1',
            'launch_url' => 'https://www.geogebra.org/lti/launch',
            'deep_linking_url' => 'https://deep.geogebra.org:8443/dl',
        ]);
        LtiTool::factory()->create([
            'oidc_login_url' => 'https://sandbox.example.test/login',
            'launch_url' => 'https://sandbox.example.test/launch',
            'deep_linking_url' => null,
        ]);
        LtiTool::factory()->create(['oidc_login_url' => 'https://off.example.test/login', 'launch_url' => 'https://off.example.test/launch', 'enabled' => false]);

        $response = $this->getJson('/api/lti/frame-origins')->assertOk();

        $this->assertSame(
            ['https://deep.geogebra.org:8443', 'https://sandbox.example.test', 'https://www.geogebra.org'],
            $response->json('data')
        );
        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    public function testOnlyHttpOriginsWithPlainHostsAreListed(): void
    {
        LtiTool::factory()->create([
            'oidc_login_url' => "javascript:alert(1)",
            'launch_url' => "https://evil.example.test; script-src *",
            'deep_linking_url' => 'ftp://files.example.test/x',
        ]);
        LtiTool::factory()->create(['oidc_login_url' => 'http://tool.localhost:8080/login', 'launch_url' => 'https://TOOL.example.test/Launch', 'deep_linking_url' => null]);

        $this->assertSame(['http://tool.localhost:8080', 'https://tool.example.test'], $this->getJson('/api/lti/frame-origins')->json('data'));
    }

    public function testItIsPublicAndEmptyWithoutTools(): void
    {
        $this->getJson('/api/lti/frame-origins')->assertOk()->assertExactJson(['success' => true, 'message' => 'LTI frame origins', 'data' => []]);
    }

    public function testTenantIsolationEachTenantListsOnlyItsOwnTools(): void
    {
        // tenants have separate databases (ADR 0007): the endpoint reads the current tenant's
        // lti_tools only; another tenant's registrations are not in this connection
        LtiTool::factory()->create(['oidc_login_url' => 'https://mine.example.test/login', 'launch_url' => 'https://mine.example.test/launch', 'deep_linking_url' => null]);

        $this->assertSame(['https://mine.example.test'], $this->getJson('http://tea.localhost/api/lti/frame-origins')->json('data'));
        $this->assertSame(0, LtiTool::query()->where('launch_url', 'like', '%coffee%')->count());
    }
}
