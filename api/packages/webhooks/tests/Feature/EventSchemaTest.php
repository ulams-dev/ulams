<?php

namespace Ulams\Webhooks\Tests\Feature;

use Opis\JsonSchema\Validator;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Models\Course;
use Ulams\Webhooks\Services\EndpointService;
use Ulams\Webhooks\Support\EventCatalog;
use Ulams\Webhooks\Tests\TestCase;

/** The published JSON Schema describes exactly what is sent (resources/schemas, served on the docs site). */
class EventSchemaTest extends TestCase
{
    use CreatesUsers;

    private function assertValid(string $json): void
    {
        $schema = json_decode((string) file_get_contents(__DIR__ . '/../../resources/schemas/webhook-event-2026-10-20.json'));
        $result = (new Validator())->validate(json_decode($json), $schema);

        $this->assertTrue($result->isValid(), $json . "\n" . json_encode($result->error()?->message()));
    }

    public function testEverySampleOfEveryTypeMatchesTheSchema(): void
    {
        [$endpoint] = $this->endpoint();
        $types = array_keys(array_filter(EventCatalog::all(), fn ($e) => in_array($e['status'], ['available', 'test'], true)));
        $this->receiver(array_fill(0, count($types), 200));

        foreach ($types as $type) {
            app(EndpointService::class)->sendTest($endpoint, $type);
        }

        $this->assertCount(count($types), $this->received);
        foreach ($this->received as $request) {
            $this->assertValid((string) $request->getBody());
        }
    }

    public function testARealEventMatchesTheSchemaAndAnInvalidOneDoesNot(): void
    {
        $this->endpoint();
        $this->receiver([200]);
        $course = new Course();
        $course->forceFill(['id' => 3, 'title' => 'Real']);

        event(new CourseFinished($this->makeStudent(), $course));

        $this->assertCount(1, $this->received);
        $body = (string) $this->received[0]->getBody();
        $this->assertValid($body);

        $broken = json_decode($body, true);
        unset($broken['data']['object']['course']);
        $result = (new Validator())->validate(json_decode(json_encode($broken)), json_decode((string) file_get_contents(__DIR__ . '/../../resources/schemas/webhook-event-2026-10-20.json')));
        $this->assertFalse($result->isValid());
    }

    public function testTheSchemaListsEveryEventType(): void
    {
        $schema = json_decode((string) file_get_contents(__DIR__ . '/../../resources/schemas/webhook-event-2026-10-20.json'), true);
        $types = array_keys(array_filter(EventCatalog::all(), fn ($e) => in_array($e['status'], ['available', 'test'], true)));

        $this->assertEqualsCanonicalizing($types, $schema['properties']['type']['enum']);
        $this->assertSame(config('ulams_webhooks.api_version'), $schema['properties']['api_version']['const']);
    }
}
