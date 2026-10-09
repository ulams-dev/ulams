<?php

namespace Tests\Integrations;

use Tests\TestCase;

/**
 * Anonymous catalogue reads are cacheable by shared caches per host; anything with credentials
 * or outside the catalogue is not.
 */
class PublicCatalogueCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // the test host is not a registered platform host
        config(['ulams_tenancy.enforce_known_hosts' => false]);
    }

    public function testAnonymousCatalogueReadsArePublic(): void
    {
        $response = $this->getJson('/api/config')->assertOk();

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('s-maxage=60', $cacheControl);
        $this->assertStringNotContainsString('private', $cacheControl);
        foreach (['Host', 'Authorization'] as $header) {
            $this->assertStringContainsString($header, (string) $response->headers->get('Vary'));
        }
    }

    public function testRequestsWithCredentialsStayPrivate(): void
    {
        $response = $this->getJson('/api/config', ['Authorization' => 'Bearer x']);

        $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    public function testOtherEndpointsAndErrorsStayPrivate(): void
    {
        $this->assertStringNotContainsString('public', (string) $this->getJson('/api/does-not-exist')->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', (string) $this->getJson('/api/courses/progress')->headers->get('Cache-Control'));
    }
}
