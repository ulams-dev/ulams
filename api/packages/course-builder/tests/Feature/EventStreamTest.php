<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\CourseBuilder\Models\Event;
use Ulams\CourseBuilder\Tests\TestCase;

class EventStreamTest extends TestCase
{
    /** @return array<int,array{id:?int,data:array}> */
    private function read(string $body): array
    {
        $events = [];
        foreach (preg_split("/\n\n/", trim($body)) as $chunk) {
            $id = null;
            $data = null;
            foreach (explode("\n", $chunk) as $line) {
                if (str_starts_with($line, 'id: ')) {
                    $id = (int) substr($line, 4);
                } elseif (str_starts_with($line, 'data: ')) {
                    $data = json_decode(substr($line, 6), true);
                }
            }
            if ($data !== null) {
                $events[] = ['id' => $id, 'data' => $data];
            }
        }

        return $events;
    }

    public function testSnapshotOnConnectThenEventsInOrderWithIds(): void
    {
        $author = $this->author();
        $session = $this->uploaded($author);

        $response = $this->actingAs($author, 'api')->get("/api/admin/course-builder/sessions/{$session->id}/events");
        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
        $events = $this->read($response->streamedContent());

        $this->assertSame('STATE_SNAPSHOT', $events[0]['data']['type']);
        $this->assertNull($events[0]['id']);
        $this->assertSame($session->id, $events[0]['data']['snapshot']['session']['id']);
        $ids = array_column(array_slice($events, 1), 'id');
        $this->assertSame(Event::query()->where('session_id', $session->id)->orderBy('id')->pluck('id')->all(), $ids);
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids);
        $types = array_map(fn ($e) => $e['data']['type'], array_slice($events, 1));
        $this->assertSame('TEXT_MESSAGE_START', $types[0]);
        $this->assertContains('RUN_FINISHED', $types);
    }

    public function testResumeWithLastEventIdSkipsDeliveredEvents(): void
    {
        $author = $this->author();
        $session = $this->uploaded($author);
        $all = Event::query()->where('session_id', $session->id)->orderBy('id')->pluck('id')->all();
        $middle = $all[intdiv(count($all), 2)];

        $events = $this->read($this->actingAs($author, 'api')->withHeaders(['Last-Event-ID' => (string) $middle])
            ->get("/api/admin/course-builder/sessions/{$session->id}/events")->streamedContent());
        $this->assertSame(array_values(array_filter($all, fn ($id) => $id > $middle)), array_column(array_slice($events, 1), 'id'));

        $viaQuery = $this->read($this->actingAs($author, 'api')->get("/api/admin/course-builder/sessions/{$session->id}/events?after={$middle}")->streamedContent());
        $this->assertSame(count($events), count($viaQuery));
    }

    public function testConnectionsAreCapped(): void
    {
        config(['course_builder.sse.max_seconds' => 1, 'course_builder.sse.poll_ms' => 200]);
        $author = $this->author();
        $session = $this->newSession($author);
        $started = microtime(true);
        $this->actingAs($author, 'api')->get("/api/admin/course-builder/sessions/{$session->id}/events")->streamedContent();
        $this->assertLessThan(3, microtime(true) - $started);
    }

    public function testA2uiSurfacesAreActivitySnapshots(): void
    {
        $author = $this->author();
        $session = $this->uploaded($author);
        $events = Event::query()->where('session_id', $session->id)->where('type', 'ACTIVITY_SNAPSHOT')->get();
        foreach ($events as $event) {
            $this->assertSame('a2ui-surface', $event->payload['activityType']);
            $messages = $event->payload['content']['messages'];
            $this->assertSame('v0.9', $messages[0]['version']);
            $this->assertSame('https://ulams.dev/catalogue/builder/v1', $messages[0]['createSurface']['catalogId']);
            $this->assertSame('root', $messages[1]['updateComponents']['components'][0]['id']);
        }
    }
}
