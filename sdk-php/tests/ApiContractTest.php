<?php

namespace Ulams\Sdk\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ulams\Sdk\Api\AdminCoursesApi;
use Ulams\Sdk\Api\AdminKnowledgeApi;
use Ulams\Sdk\Api\AdminWebhooksApi;
use Ulams\Sdk\Model\KnowledgeExportsCreateRequest;
use Ulams\Sdk\Model\WebhooksEndpointsCreateRequest;

/**
 * The generated client against canned answers: each case checks the request an operation builds (method, path,
 * query, body) and that the answer comes back as the generated model.
 */
class ApiContractTest extends TestCase
{
    private function envelope(mixed $data, string $message = ''): array
    {
        return ['success' => true, 'message' => $message, 'data' => $data];
    }

    public function testListAnswerIsAModelWithItsRows(): void
    {
        $mock = new MockedClient([MockedClient::json($this->envelope([['id' => 'e1', 'scope' => 'course', 'course_id' => 12, 'status' => 'ready', 'chunks' => 9, 'uncited_chunks' => 1]]))]);

        $answer = $mock->client->api(AdminKnowledgeApi::class)->knowledgeExportsList(course_id: 12);

        $this->assertSame('course_id=12', $mock->request()->getUri()->getQuery());
        $this->assertTrue($answer->getSuccess());
        $this->assertCount(1, $answer->getData());
        $this->assertSame('ready', $answer->getData()[0]->getStatus());
        $this->assertSame(9, $answer->getData()[0]->getChunks());
    }

    public function testCreateSendsTheJsonBody(): void
    {
        $mock = new MockedClient([MockedClient::json($this->envelope(['id' => 'e2', 'scope' => 'course', 'status' => 'queued']), 202)]);

        $answer = $mock->client->api(AdminKnowledgeApi::class)->knowledgeExportsCreate(new KnowledgeExportsCreateRequest(['scope' => 'course', 'course_id' => 12]));

        $request = $mock->request();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/admin/knowledge/exports', $request->getUri()->getPath());
        $this->assertStringContainsString('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertEquals(['scope' => 'course', 'course_id' => 12], json_decode((string) $request->getBody(), true));
        $this->assertSame('queued', $answer->getData()->getStatus());
    }

    public function testPathParametersAreFilledIn(): void
    {
        $mock = new MockedClient([MockedClient::json($this->envelope(['id' => 'e3', 'status' => 'ready']))]);

        $mock->client->api(AdminKnowledgeApi::class)->knowledgeCoursesLatest(42);

        $this->assertSame('/api/admin/knowledge/courses/42/latest', $mock->request()->getUri()->getPath());
    }

    public function testDeleteAndEventsOfAnotherPackage(): void
    {
        $mock = new MockedClient([
            MockedClient::json($this->envelope([['type' => 'course.completed', 'description' => 'A learner finished a course.', 'status' => 'available']])),
            MockedClient::json(['success' => true, 'message' => 'Webhook endpoint deleted.']),
        ]);
        $api = $mock->client->api(AdminWebhooksApi::class);

        $events = $api->webhooksEventsList();
        $api->webhooksEndpointsDelete('01j9z8x3v7c5qk2m4n6p8r0t1w');

        $this->assertSame('course.completed', $events->getData()[0]->getType());
        $this->assertSame('DELETE', $mock->request(1)->getMethod());
        $this->assertSame('/api/admin/webhooks/endpoints/01j9z8x3v7c5qk2m4n6p8r0t1w', $mock->request(1)->getUri()->getPath());
    }

    public function testEndpointCreateBody(): void
    {
        $mock = new MockedClient([MockedClient::json($this->envelope(['id' => 'w1', 'url' => 'https://hooks.example.com/u', 'events' => ['course.completed']]), 201)]);

        $mock->client->api(AdminWebhooksApi::class)->webhooksEndpointsCreate(new WebhooksEndpointsCreateRequest(['url' => 'https://hooks.example.com/u', 'events' => ['course.completed']]));

        $this->assertEquals(['url' => 'https://hooks.example.com/u', 'events' => ['course.completed']], json_decode((string) $mock->request()->getBody(), true));
    }

    public function testCourseListQueryAndLooselyTypedData(): void
    {
        $mock = new MockedClient([MockedClient::json($this->envelope([['id' => 1, 'title' => 'Latte art']]) + ['meta' => ['current_page' => 2, 'last_page' => 3, 'total' => 31]])]);

        $answer = $mock->client->api(AdminCoursesApi::class)->adminCoursesGet(page: 2, per_page: 10, title: 'Latte');

        parse_str($mock->request()->getUri()->getQuery(), $query);
        $this->assertSame(['page' => '2', 'per_page' => '10', 'title' => 'Latte'], array_intersect_key($query, array_flip(['page', 'per_page', 'title'])));
        $this->assertNotNull($answer);
    }

    public function testErrorsBecomeApiExceptions(): void
    {
        $mock = new MockedClient([MockedClient::json(['success' => false, 'message' => 'Knowledge exports need the knowledge_manage permission.'], 403)]);

        try {
            $mock->client->api(AdminKnowledgeApi::class)->knowledgeExportsList();
            $this->fail('403 must throw');
        } catch (\Ulams\Sdk\ApiException $e) {
            $this->assertSame(403, $e->getCode());
            $this->assertStringContainsString('knowledge_manage', (string) $e->getResponseBody());
        }
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function specOperations(): array
    {
        $spec = json_decode((string) file_get_contents(__DIR__ . '/../../front/cli/spec/openapi.json'), true, 512, JSON_THROW_ON_ERROR);
        $cases = [];
        foreach ($spec['paths'] as $path => $item) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (isset($item[$method])) {
                    $cases["$method $path"] = [$method, $path];
                }
            }
        }

        return $cases;
    }

    public function testEveryOperationOfTheSpecHasAGeneratedMethod(): void
    {
        $operations = count(self::specOperations());
        $names = [];
        foreach (glob(__DIR__ . '/../lib/Api/*.php') as $file) {
            preg_match_all('/public function (\w+)WithHttpInfo\(/', (string) file_get_contents($file), $found);
            foreach ($found[1] as $name) {
                if (!str_ends_with($name, 'Async')) {
                    $names[$name] = true; // an operation with two tags is generated into both classes
                }
            }
        }
        $methods = count($names);

        $this->assertGreaterThan(600, $operations);
        $this->assertSame($operations, $methods, 'sdk-php/lib is out of date: run sdk-php/generate.sh');
    }

    public function testEveryRouteOfTheSpecIsBuiltByTheGeneratedClients(): void
    {
        $paths = [];
        foreach (glob(__DIR__ . '/../lib/Api/*.php') as $file) {
            preg_match_all("/\\\$resourcePath = '([^']+)'/", (string) file_get_contents($file), $found);
            foreach ($found[1] as $p) {
                $paths[$p] = true;
            }
        }
        $missing = array_values(array_filter(array_column(self::specOperations(), 1), fn (string $p) => !isset($paths[$p])));

        $this->assertSame([], array_values(array_unique($missing)));
    }
}
