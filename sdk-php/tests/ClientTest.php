<?php

namespace Ulams\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use Ulams\Sdk\Api\AdminKnowledgeApi;
use Ulams\Sdk\Model\KnowledgeExportsCreateRequest;

class ClientTest extends TestCase
{
    public function testRequestsGoToTheBaseUrlWithTheBearerToken(): void
    {
        $mock = new MockedClient([MockedClient::json(['success' => true, 'message' => '', 'data' => []])]);

        $mock->client->api(AdminKnowledgeApi::class)->knowledgeExportsList();

        $request = $mock->request();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('academy.test', $request->getUri()->getHost());
        $this->assertSame('https', $request->getUri()->getScheme());
        $this->assertSame('/api/admin/knowledge/exports', $request->getUri()->getPath());
        $this->assertSame('Bearer secret-token', $request->getHeaderLine('Authorization'));
        $this->assertStringContainsString('application/json', $request->getHeaderLine('Accept'));
    }

    public function testNoTokenMeansNoAuthorizationHeader(): void
    {
        $mock = new MockedClient([MockedClient::json(['success' => true, 'message' => '', 'data' => []])], null);

        $mock->client->api(AdminKnowledgeApi::class)->knowledgeExportsList();

        $this->assertFalse($mock->request()->hasHeader('Authorization'));
    }

    public function testReadsGetARequestIdButNoIdempotencyKey(): void
    {
        $mock = new MockedClient([MockedClient::json(['success' => true, 'message' => '', 'data' => []])]);

        $mock->client->api(AdminKnowledgeApi::class)->knowledgeExportsList();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $mock->request()->getHeaderLine('X-Request-Id'));
        $this->assertFalse($mock->request()->hasHeader('Idempotency-Key'));
    }

    public function testWritesGetAFreshIdempotencyKeyEach(): void
    {
        $answer = fn () => MockedClient::json(['success' => true, 'message' => 'Export queued.', 'data' => ['id' => 'x', 'scope' => 'tenant', 'status' => 'queued']], 202);
        $mock = new MockedClient([$answer(), $answer()]);
        $api = $mock->client->api(AdminKnowledgeApi::class);

        $api->knowledgeExportsCreate(new KnowledgeExportsCreateRequest(['scope' => 'tenant']));
        $api->knowledgeExportsCreate(new KnowledgeExportsCreateRequest(['scope' => 'tenant']));

        $first = $mock->request(0)->getHeaderLine('Idempotency-Key');
        $this->assertNotSame('', $first);
        $this->assertNotSame($first, $mock->request(1)->getHeaderLine('Idempotency-Key'));
    }

    public function testAnIdempotencyKeyYouSetIsKept(): void
    {
        $mock = new MockedClient([MockedClient::json(['success' => true, 'message' => '', 'data' => []], 202)]);
        $api = new AdminKnowledgeApi($mock->client->http(), $mock->client->configuration());

        $request = $api->knowledgeExportsCreateRequest(new KnowledgeExportsCreateRequest(['scope' => 'tenant']))->withHeader('Idempotency-Key', 'mine-1');
        $mock->client->http()->send($request);

        $this->assertSame('mine-1', $mock->request()->getHeaderLine('Idempotency-Key'));
    }
}
